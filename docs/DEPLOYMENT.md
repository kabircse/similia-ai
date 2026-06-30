# Similia AI Deployment Guide

## Production Stack

- Nginx reverse proxy
- React frontend static container
- Laravel API PHP-FPM container
- Laravel backend Nginx container
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

## Migrate

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
```

## Demo Seed

Run this only for a demo deployment:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan db:seed --force
```

## Logs

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f
```

For a single service:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f proxy
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend
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
APP_URL=http://localhost
FRONTEND_URL=http://localhost
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

Then build and start:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan db:seed --force
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
APP_URL=http://localhost
FRONTEND_URL=http://localhost
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

For a real domain:

```env
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

## HTTPS Notes

For HTTPS, use one of:

- Cloudflare proxy SSL
- Nginx with Certbot
- Caddy reverse proxy
- Traefik reverse proxy

For a first VPS demo deployment, Cloudflare proxy SSL is the simplest.
