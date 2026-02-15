# Deploy Barberflow API em VPS (Docker)

Este guia descreve como fazer o deploy da API em uma VPS usando a imagem Docker de produção.

## O que foi criado

- **`Dockerfile`** – Imagem única com PHP 8.3-FPM + Nginx (multi-stage, otimizada para produção).
- **`.dockerignore`** – Reduz o contexto de build (exclui vendor, .env, testes, etc.).
- **`docker-compose.prod.yml`** – Sobe a API (porta 80) + MySQL + Evolution API (porta 8084) + Redis.
- **`docker/php/php-prod.ini`** – Ajustes de PHP para produção (opcache, erros desligados).
- **`docker/php/fpm-pool.conf`** – Pool PHP-FPM rodando como usuário `app`.
- **`docker/nginx/prod.conf`** – Nginx para uso dentro da mesma imagem.
- **`docker/docker-entrypoint.sh`** – Inicia PHP-FPM e Nginx no mesmo container.

## Requisitos na VPS

- Docker 20.10+
- Docker Compose 2.0+
- Porta 80 livre (ou altere o mapeamento em `docker-compose.prod.yml`).

## Passo a passo

### 1. Clonar/copiar o projeto na VPS

```bash
# Exemplo: clone ou upload do código
git clone <seu-repositorio> barberflow-api
cd barberflow-api
```

### 2. Criar o arquivo `.env` de produção

Na raiz do projeto, crie `.env` (não versionado) com as variáveis necessárias:

```env
# Obrigatórios
APP_SECRET=uma_chave_secreta_longa_e_aleatoria
DB_PASSWORD=sua_senha_mysql_forte
DB_ROOT_PASSWORD=senha_root_mysql
JWT_PASSPHRASE=frase_secreta_para_jwt

# Opcionais (valores padrão abaixo)
DB_NAME=barberflow
DB_USER=barberflow
JWT_TOKEN_TTL=3600
CORS_ALLOW_ORIGIN='^https?://.*$'

# Evolution API (WhatsApp) – já incluída no compose prod na porta 8084
EVOLUTION_API_KEY=sua_chave_secreta_evolution
# A API Barberflow usa internamente http://evolution_api:8080 (não precisa mudar)
```

**Importante:** não use os valores de exemplo em produção; gere chaves e senhas fortes.

### 3. Gerar chaves JWT

As chaves não vão na imagem; são montadas por volume a partir de `config/jwt/` na VPS.

```bash
mkdir -p config/jwt

# Gerar chave privada (use o mesmo JWT_PASSPHRASE do .env)
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa \
  -pkeyopt rsa_keygen_bits:4096 -pass pass:SUA_JWT_PASSPHRASE

# Gerar chave pública
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout \
  -passin pass:SUA_JWT_PASSPHRASE

chmod 644 config/jwt/public.pem
chmod 600 config/jwt/private.pem
```

### 4. Build e subir os containers

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

- **API:** http://IP_DA_VPS (porta 80)
- **Evolution API (WhatsApp):** http://IP_DA_VPS:8084

### 5. Rodar migrations

Após o primeiro `up`, execute as migrations (um comando único, entrypoint desabilitado):

```bash
docker compose -f docker-compose.prod.yml run --rm --entrypoint "" api \
  php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. (Opcional) Limpar cache de produção

Se precisar limpar o cache após mudar config ou código:

```bash
docker compose -f docker-compose.prod.yml run --rm --entrypoint "" api \
  php bin/console cache:clear --env=prod
```

## Comandos úteis

| Ação | Comando |
|------|--------|
| Ver logs da API | `docker compose -f docker-compose.prod.yml logs -f api` |
| Parar tudo | `docker compose -f docker-compose.prod.yml down` |
| Rebuild e subir | `docker compose -f docker-compose.prod.yml up -d --build` |
| Entrar no container | `docker compose -f docker-compose.prod.yml exec api sh` |

## Banco de dados

- O MySQL sobe junto no `docker-compose.prod.yml` e persiste em volume `barberflow_db_data`.
- Se quiser usar um MySQL externo (já existente na VPS), altere no `.env`:
  - `DATABASE_URL` não é lido do .env pelo Symfony (vem de `DATABASE_URL`). No compose já definimos `DATABASE_URL` a partir de `DB_*`. Para DB externo, defina no compose a variável `DATABASE_URL` completa, por exemplo:
  - `DATABASE_URL=mysql://user:pass@host:3306/barberflow?serverVersion=8.0&charset=utf8mb4`
  - e remova ou comente o serviço `database` e o `depends_on` do serviço `api`.

## Evolution API (WhatsApp)

O `docker-compose.prod.yml` já inclui a Evolution API e o Redis:

- **Evolution API** roda na **porta 8084** (acesso externo: `http://IP_DA_VPS:8084`).
- A Barberflow API fala com ela internamente em `http://evolution_api:8080` (já configurado).
- Defina no `.env` a chave: `EVOLUTION_API_KEY=sua_chave_secreta_evolution` (use a mesma chave ao configurar instâncias no painel da Evolution).
- O banco `evolution` no MySQL é criado automaticamente pelo script `docker/mysql/init-evolution.sql`.

## Segurança em produção

- Use HTTPS na frente da API (Nginx/Caddy na host ou proxy reverso).
- Mantenha `APP_SECRET`, `DB_PASSWORD`, `JWT_PASSPHRASE` e chaves JWT em segredo e nunca no repositório.
- Restrinja `CORS_ALLOW_ORIGIN` aos domínios do seu front (ex.: `'^https://(www\.)?seusite\.com$'`).
- Atualize a imagem e o sistema da VPS com frequência.

## Build apenas da imagem (sem compose)

Para apenas construir e publicar a imagem (ex.: em um registry):

```bash
docker build -t barberflow-api:latest .
```

Para rodar só a API (sem MySQL no compose), use outro compose ou defina `DATABASE_URL` para um banco externo e execute:

```bash
docker run -d -p 80:80 \
  -e APP_ENV=prod \
  -e APP_SECRET=... \
  -e DATABASE_URL=mysql://... \
  -e JWT_PASSPHRASE=... \
  -v $(pwd)/config/jwt:/var/www/config/jwt:ro \
  --name barberflow_api \
  barberflow-api:latest
```

(Opcional: use `--env-file .env` se tiver um arquivo `.env` só com variáveis de ambiente.)
