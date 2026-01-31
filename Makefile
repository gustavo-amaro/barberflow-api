# BarberFlow API - Makefile
# Comandos úteis para desenvolvimento

.PHONY: help build up down restart logs shell composer db-create db-migrate db-seed jwt-keys

# Cores
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

## Ajuda
help: ## Mostra esta ajuda
	@echo ''
	@echo 'Uso:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<comando>${RESET}'
	@echo ''
	@echo 'Comandos:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  ${YELLOW}%-15s${GREEN}%s${RESET}\n", $$1, $$2}' $(MAKEFILE_LIST)

## Docker
build: ## Constrói os containers Docker
	docker compose build

up: ## Inicia os containers Docker
	docker compose up -d

down: ## Para os containers Docker
	docker compose down

restart: down up ## Reinicia os containers Docker

logs: ## Mostra os logs dos containers
	docker compose logs -f

## Shell
shell: ## Acessa o shell do container PHP
	docker compose exec php sh

shell-db: ## Acessa o shell do container MySQL
	docker compose exec database mysql -u barberflow -pbarberflow123 barberflow

## Composer
composer-install: ## Instala as dependências do Composer
	docker compose exec php composer install

composer-update: ## Atualiza as dependências do Composer
	docker compose exec php composer update

## Banco de Dados
db-create: ## Cria o banco de dados
	docker compose exec php php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Executa as migrations
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

db-diff: ## Gera uma nova migration
	docker compose exec php php bin/console doctrine:migrations:diff

db-schema: ## Atualiza o schema diretamente (dev only)
	docker compose exec php php bin/console doctrine:schema:update --force

db-fixtures: ## Carrega os fixtures
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

## JWT
jwt-keys: ## Gera as chaves JWT
	docker compose exec php mkdir -p config/jwt
	docker compose exec php openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:barberflow_jwt_passphrase
	docker compose exec php openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:barberflow_jwt_passphrase
	docker compose exec php chmod 644 config/jwt/private.pem config/jwt/public.pem

## Cache
cache-clear: ## Limpa o cache
	docker compose exec php php bin/console cache:clear

## Testes
test: ## Executa os testes
	docker compose exec php php bin/phpunit

## Setup inicial
setup: build up composer-install db-create db-migrate jwt-keys ## Setup completo do projeto
	@echo ''
	@echo '${GREEN}Setup concluído!${RESET}'
	@echo ''
	@echo 'Acesse: ${YELLOW}http://localhost:8080${RESET}'
	@echo 'Adminer: ${YELLOW}http://localhost:8081${RESET}'
	@echo ''
