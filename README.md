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

## Notificações WhatsApp

O sistema envia notificações via WhatsApp (Evolution API) nos seguintes momentos:

| Evento | Quem recebe | Conteúdo |
|--------|--------------|----------|
| Cliente agenda (página pública ou painel) | Barbearia | Novo agendamento pendente de confirmação |
| Barbearia confirma o agendamento | Cliente | Confirmação com data, horário e serviço |
| 30 min antes do horário confirmado | Barbearia | Lembrete do agendamento |

### Subir Evolution API com MySQL (Docker)

O `docker-compose` já inclui a Evolution API usando o **mesmo MySQL** do Barberflow (banco `evolution`) e Redis:

```bash
docker compose up -d
```

- **Evolution API**: http://localhost:8084  
- **Chave padrão** (no `.env`): `EVOLUTION_API_KEY=evolution_secret_key`

Se o MySQL já existia antes de adicionar o script de init, crie o banco manualmente:

```bash
docker compose exec database mysql -u barberflow -pbarberflow123 -e "CREATE DATABASE IF NOT EXISTS evolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

No `.env` da API, use apenas a URL base (cada barbearia tem sua própria instância, configurada no painel):

```env
WHATSAPP_EVOLUTION_BASE_URL=http://evolution_api:8080
```

*(Se a API PHP rodar no host, use `http://localhost:8084`.)*

**Conexão por barbearia:** no painel admin, em **Configurações**, a barbearia clica em **Conectar WhatsApp**, escaneia o QR code com o celular e passa a receber e enviar notificações.

### Configuração (Evolution API externa)

1. Se não for usar o Docker acima, configure uma instância na [Evolution API](https://evolution-api.com) (self-hosted ou hospedada).
2. No `.env` da API, preencha:

```env
WHATSAPP_EVOLUTION_BASE_URL=https://sua-evolution-api.com
EVOLUTION_API_KEY=sua-api-key-global
```

3. **Cadastre o telefone da barbearia** no painel (dados da barbearia): o número é usado para receber “novo agendamento” e “lembrete em 30 min”.
4. Os clientes precisam informar o telefone no agendamento para receber a confirmação.

### Lembrete 30 minutos antes (cron)

Para enviar o lembrete à barbearia cerca de 30 minutos antes do horário, execute o comando a cada 5–10 minutos, por exemplo:

```bash
# A cada 5 minutos (Linux/Mac crontab)
*/5 * * * * docker compose -f /caminho/barberflow-api/docker-compose.yml exec -T php php bin/console app:appointment-reminders
```

Ou com Make (se existir target):

```bash
php bin/console app:appointment-reminders
```

Se as variáveis WhatsApp no `.env` estiverem vazias, as notificações ficam desativadas e o sistema segue funcionando normalmente.

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
