# Similia AI Deployment Guide

## Production Stack

- Caddy HTTPS reverse proxy
- React frontend static container
- Laravel API PHP-FPM container
- Laravel backend Nginx container
- Laravel queue worker container
- Laravel scheduler container
- FastAPI AI service container
- PostgreSQL pgvector
- Redis

## Environment File

Create:

```bash
cp .env.production.example .env.production
```

Update:

- `APP_KEY`
- `APP_DOMAIN`
- `ACME_EMAIL`
- `APP_URL`
- `FRONTEND_URL`
- `POSTGRES_PASSWORD`
- `SESSION_DOMAIN`
- `SANCTUM_STATEFUL_DOMAINS`

Generate `APP_KEY` from the Laravel app:

```bash
cd apps/backend
php artisan key:generate --show
```

Copy the output into `.env.production`.

## Start Production

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

After the first setup, you can deploy updates with:

```bash
./scripts/deploy-prod.sh
```

By default, the script pulls `main`. To deploy from another branch:

```bash
DEPLOY_BRANCH=dev ./scripts/deploy-prod.sh
```

## HTTPS with Caddy

Production HTTPS is handled by Caddy.

Caddy automatically requests and renews TLS certificates when:

- `APP_DOMAIN` is a real domain
- DNS points to the VPS IP
- ports `80` and `443` are open
- `ACME_EMAIL` is set

Example production env:

```env
APP_DOMAIN=similia.example.com
ACME_EMAIL=admin@example.com
APP_URL=https://similia.example.com
FRONTEND_URL=https://similia.example.com
SESSION_DOMAIN=similia.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=similia.example.com,www.similia.example.com
```

Start:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Check Caddy logs:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f proxy
```

If HTTPS does not work, check:

- domain DNS A record
- VPS firewall
- ports `80` and `443`
- `APP_DOMAIN` value
- Caddy logs

## Migrate

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
```

## Demo Seed

Run this only for a demo deployment:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan db:seed --force
```

## Queue Workers

Production queue worker service:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-queue
```

Production scheduler service:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-scheduler
```

Restart queue workers:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan queue:restart
```

Check queue health:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan queue:health
```

List failed jobs:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan queue:failed
```

Retry failed jobs:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan queue:retry all
```

## Logs

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f
```

For a single service:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f proxy
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-queue
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend-scheduler
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f ai-service
```

## Backup

```bash
./scripts/backup-postgres.sh
```

Backups are written to `backups/`, which is intentionally ignored by git.

## Local Production Test

Create a local production env file:

```bash
cp .env.production.example .env.production
```

Use these local values:

```env
APP_DOMAIN=:80
ACME_EMAIL=admin@example.com
APP_URL=http://localhost
FRONTEND_URL=http://localhost
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

Then build and start:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml -f docker-compose.https-local.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan db:seed --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan queue:health
```

Open:

```text
http://localhost
```

Demo login:

```text
doctor@similia.test
password
```

## Common Fixes

If frontend login fails, check:

- `APP_URL`
- `FRONTEND_URL`
- `SESSION_DOMAIN`
- `SESSION_SECURE_COOKIE`
- `SANCTUM_STATEFUL_DOMAINS`

For local Docker production testing:

```env
APP_DOMAIN=:80
ACME_EMAIL=admin@example.com
APP_URL=http://localhost
FRONTEND_URL=http://localhost
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

For a real domain:

```env
APP_DOMAIN=your-domain.com
ACME_EMAIL=admin@your-domain.com
APP_URL=https://your-domain.com
FRONTEND_URL=https://your-domain.com
SESSION_DOMAIN=your-domain.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com
```

If Laravel storage permissions fail:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan optimize:clear
```

If the database is not ready:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs postgres
```

For first VPS deployment, point DNS to the VPS before starting Caddy so certificate issuance can complete.
