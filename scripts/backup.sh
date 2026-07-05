#!/usr/bin/env bash
# Dagelijkse backup van MySQL + media naar /home/rzvg/backups.
# Standaard retention: 30 dagen. Wordt vanuit cron gedraaid.
#
#   0 3 * * * bash /var/www/rzvg/scripts/backup.sh >> /var/log/rzvg-backup.log 2>&1

set -euo pipefail

REPO_DIR="${REPO_DIR:-$(dirname "$(dirname "$(readlink -f "$0")")")}"
BACKUP_DIR="${BACKUP_DIR:-/home/rzvg/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

cd "$REPO_DIR"
mkdir -p "$BACKUP_DIR"

# .env inlezen zodat DB_DATABASE / DB_ROOT_PASSWORD beschikbaar zijn.
set -a
# shellcheck disable=SC1091
source .env
set +a

DATE=$(date +%F-%H%M)
COMPOSE="docker compose -f docker-compose.prod.yml"

echo "==> [$DATE] MySQL-dump"
$COMPOSE exec -T db \
    mysqldump -u root -p"$DB_ROOT_PASSWORD" \
    --single-transaction --routines --triggers "$DB_DATABASE" \
    | gzip > "$BACKUP_DIR/db-$DATE.sql.gz"

echo "==> [$DATE] Media-tarball"
# Media-volume rechtstreeks naar tar; snel en compact.
docker run --rm \
    -v rzvg_media_data:/data:ro \
    -v "$BACKUP_DIR":/backup \
    alpine:3 \
    tar czf "/backup/media-$DATE.tar.gz" -C /data .

echo "==> [$DATE] Retention ($RETENTION_DAYS dagen)"
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz' -mtime +"$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'media-*.tar.gz' -mtime +"$RETENTION_DAYS" -delete

echo "==> [$DATE] Overzicht"
ls -lh "$BACKUP_DIR" | tail -n +2

echo "==> [$DATE] Klaar"
