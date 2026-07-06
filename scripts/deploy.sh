#!/usr/bin/env bash
# Deploy-script voor de test/prod-server. Idempotent: kan bij elke update
# opnieuw worden gedraaid. Wordt uitgevoerd als deploy-user (rzvg).
#
# Werkwijze:
#   1. Pull latest code
#   2. Build assets (via node-container, éénmalig)
#   3. Bouw + start containers via docker-compose.prod.yml
#   4. Migreer database
#   5. Seed permissies/rollen/sjablonen (idempotent via updateOrCreate)
#   6. Cache warm
#   7. Restart queue-worker (voor code-wijzigingen in queued jobs)

set -euo pipefail

REPO_DIR="${REPO_DIR:-$(dirname "$(dirname "$(readlink -f "$0")")")}"
cd "$REPO_DIR"

# DEPLOY_STACK bepaalt welke compose-file wordt gebruikt:
#   test (default) -> docker-compose.prod.yml
#   acc            -> docker-compose.acc.yml
DEPLOY_STACK="${DEPLOY_STACK:-test}"
case "$DEPLOY_STACK" in
    test) COMPOSE_FILE="docker-compose.prod.yml" ;;
    acc)  COMPOSE_FILE="docker-compose.acc.yml" ;;
    *) echo "Onbekende DEPLOY_STACK: $DEPLOY_STACK" >&2; exit 1 ;;
esac

COMPOSE="docker compose -f $COMPOSE_FILE"
echo "==> Deploy voor stack '$DEPLOY_STACK' via $COMPOSE_FILE"

echo "==> Repo up-to-date maken"
git pull --ff-only

echo "==> Assets bouwen (Vite/Tailwind)"
# Gebruikt een tijdelijk node-container zodat we geen node op de host nodig
# hebben. Public/build en public/hot worden op de host geplaatst.
docker run --rm \
    -v "$PWD":/app \
    -w /app \
    node:22-alpine \
    sh -c 'npm ci && npm run build'

echo "==> Containers bouwen"
$COMPOSE build

echo "==> Stack up"
$COMPOSE up -d

echo "==> Wachten tot app-container gezond is"
sleep 5

echo "==> Composer install (--no-dev)"
$COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Storage-link"
$COMPOSE exec -T app php artisan storage:link || true

echo "==> Migreren"
$COMPOSE exec -T app php artisan migrate --force

echo "==> Seeders (idempotent — permissies, rollen, sjablonen, review-policies, lidmaatschapsvormen, field-definitions)"
$COMPOSE exec -T app php artisan db:seed --force

echo "==> Config- en route-cache"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Queue restart"
$COMPOSE exec -T app php artisan queue:restart

echo
echo "=== Deploy klaar ==="
