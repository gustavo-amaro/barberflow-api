# ============================================================
# Barberflow API - Imagem Docker para produção (VPS)
# ============================================================
# Build: docker build -t barberflow-api:latest .
# ============================================================

# ---- Stage 1: Builder (dependências e assets) ----
FROM php:8.3-cli-alpine AS builder

RUN apk add --no-cache \
    git \
    unzip \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        intl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copia o código (vendor e arquivos desnecessários são excluídos via .dockerignore)
COPY . .

# Instala dependências de produção (sem dev)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

RUN composer dump-autoload --optimize --classmap-authoritative

# ---- Stage 2: Runtime (PHP-FPM + Nginx - uma única imagem) ----
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    libpng \
    oniguruma \
    libxml2 \
    icu-libs \
    tzdata

# Deploy de extensões PHP (precisam de -dev para compilar)
RUN apk add --no-cache --virtual .build-deps \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        intl \
        opcache \
    && apk del .build-deps

# PHP production ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-barberflow.ini

# PHP-FPM: pool como usuário app (para escrever em var/)
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Nginx: config para mesma imagem (PHP-FPM em 127.0.0.1:9000)
RUN rm -f /etc/nginx/http.d/default.conf
COPY docker/nginx/prod.conf /etc/nginx/http.d/default.conf

# Entrypoint: inicia PHP-FPM + Nginx
COPY docker/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Usuário não-root para a aplicação
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

WORKDIR /var/www

# Copia a aplicação do builder (com vendor)
COPY --from=builder --chown=app:app /app /var/www

# Diretórios que precisam de escrita (cache, logs, JWT)
RUN mkdir -p var/cache var/log config/jwt \
    && chown -R app:app var config/jwt

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
