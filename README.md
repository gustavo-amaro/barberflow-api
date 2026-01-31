# BarberFlow API

Sistema de agendamento e gestão para barbearias desenvolvido com Symfony 7.2.

## Tecnologias

- **PHP 8.3** - Linguagem de programação
- **Symfony 7.2** - Framework PHP
- **MySQL 8.0** - Banco de dados
- **Docker & Docker Compose** - Containerização
- **JWT** - Autenticação

## Requisitos

- Docker 20.10+
- Docker Compose 2.0+
- Make (opcional, mas recomendado)

## Instalação Rápida

```bash
# Clone o repositório
git clone <url-do-repositorio>
cd barberflow-api

# Execute o setup completo
make setup

# Ou manualmente:
docker compose build
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:schema:update --force
```

## Gerando Chaves JWT

```bash
make jwt-keys

# Ou manualmente:
docker compose exec php mkdir -p config/jwt
docker compose exec php openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:barberflow_jwt_passphrase
docker compose exec php openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:barberflow_jwt_passphrase
```

## URLs

| Serviço | URL |
|---------|-----|
| API | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |

## Comandos Úteis

```bash
# Ver todos os comandos disponíveis
make help

# Iniciar containers
make up

# Parar containers
make down

# Ver logs
make logs

# Acessar shell do PHP
make shell

# Executar migrations
make db-migrate

# Limpar cache
make cache-clear
```

## Endpoints da API

### Autenticação

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/register` | Registrar usuário |
| POST | `/api/login` | Login (retorna JWT) |
| GET | `/api/me` | Dados do usuário logado |

### Barbearia (Shop)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/shops` | Criar barbearia |
| GET | `/api/shops` | Ver barbearia do usuário |
| PUT | `/api/shops` | Atualizar barbearia |
| GET | `/api/shops/public/{slug}` | Ver barbearia pública |

### Barbeiros (Barbers)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/barbers` | Listar barbeiros |
| POST | `/api/barbers` | Criar barbeiro |
| GET | `/api/barbers/{id}` | Ver barbeiro |
| PUT | `/api/barbers/{id}` | Atualizar barbeiro |
| DELETE | `/api/barbers/{id}` | Remover barbeiro |

### Serviços (Services)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/services` | Listar serviços |
| POST | `/api/services` | Criar serviço |
| GET | `/api/services/{id}` | Ver serviço |
| PUT | `/api/services/{id}` | Atualizar serviço |
| DELETE | `/api/services/{id}` | Remover serviço |

### Produtos (Products)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/products` | Listar produtos |
| POST | `/api/products` | Criar produto |
| GET | `/api/products/{id}` | Ver produto |
| PUT | `/api/products/{id}` | Atualizar produto |
| DELETE | `/api/products/{id}` | Remover produto |
| PATCH | `/api/products/{id}/stock` | Atualizar estoque |

### Clientes (Clients)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/clients` | Listar clientes |
| GET | `/api/clients/top` | Top clientes |
| POST | `/api/clients` | Criar cliente |
| GET | `/api/clients/{id}` | Ver cliente |
| PUT | `/api/clients/{id}` | Atualizar cliente |
| DELETE | `/api/clients/{id}` | Remover cliente |

### Agendamentos (Appointments)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/appointments` | Listar agendamentos |
| GET | `/api/appointments/pending` | Agendamentos pendentes |
| POST | `/api/appointments` | Criar agendamento |
| GET | `/api/appointments/{id}` | Ver agendamento |
| PUT | `/api/appointments/{id}` | Atualizar agendamento |
| DELETE | `/api/appointments/{id}` | Remover agendamento |
| POST | `/api/appointments/{id}/confirm` | Confirmar agendamento |
| POST | `/api/appointments/{id}/complete` | Concluir agendamento |
| POST | `/api/appointments/{id}/cancel` | Cancelar agendamento |

### Dashboard

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/dashboard/stats` | Estatísticas gerais |
| GET | `/api/dashboard/appointments/today` | Agendamentos de hoje |
| GET | `/api/dashboard/revenue` | Receita por período |

## Estrutura do Projeto

```
barberflow-api/
├── config/              # Configurações do Symfony
├── docker/              # Arquivos Docker
│   ├── nginx/          # Configuração do Nginx
│   └── php/            # Dockerfile do PHP
├── migrations/          # Migrations do banco
├── public/             # Ponto de entrada (index.php)
├── src/
│   ├── Controller/     # Controllers da API
│   ├── Entity/         # Entidades Doctrine
│   └── Repository/     # Repositories
├── templates/          # Templates Twig (se necessário)
├── tests/              # Testes
├── var/                # Cache e logs
├── vendor/             # Dependências
├── .env                # Variáveis de ambiente
├── composer.json       # Dependências PHP
├── docker-compose.yml  # Configuração Docker
├── Makefile           # Comandos úteis
└── README.md          # Este arquivo
```

## Variáveis de Ambiente

```env
# Aplicação
APP_ENV=dev
APP_SECRET=change_this_to_a_secure_secret_key

# Banco de Dados
DATABASE_URL="mysql://barberflow:barberflow123@database:3306/barberflow?serverVersion=8.0&charset=utf8mb4"

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=barberflow_jwt_passphrase
JWT_TOKEN_TTL=3600

# CORS
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

## Exemplo de Uso

### Registrar um usuário

```bash
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@email.com",
    "password": "senha123"
  }'
```

### Fazer login

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "joao@email.com",
    "password": "senha123"
  }'
```

### Usar o token JWT

```bash
curl -X GET http://localhost:8080/api/me \
  -H "Authorization: Bearer SEU_TOKEN_JWT"
```

## Licença

Proprietário - Todos os direitos reservados.
