#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=".env.production"
COMPOSE_FILE="docker-compose.prod.yml"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE. Copy .env.production.example and fill in production values first."
  exit 1
fi

echo "Pulling latest code from $DEPLOY_BRANCH..."
git pull origin "$DEPLOY_BRANCH"

echo "Building and starting containers..."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --build

echo "Running migrations..."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec backend php artisan migrate --force

echo "Optimizing Laravel..."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec backend php artisan optimize:clear
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec backend php artisan optimize

echo "Restarting Laravel queue workers..."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec backend php artisan queue:restart || true

echo "Checking queue health..."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec backend php artisan queue:health || true

echo "Deployment complete."
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" ps
