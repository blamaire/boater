#!/usr/bin/env bash
# Auto-deploy watcher voor de test-branch. Draait vanuit cron elke minuut
# en deployt zodra er nieuwe commits op origin/test staan.
#
#   * * * * * flock -n /tmp/rzvg-auto-deploy.lock bash /var/www/rzvg/scripts/auto-deploy.sh >> /var/log/rzvg-auto-deploy.log 2>&1
#
# flock voorkomt dat een lang-lopende deploy een tweede parallelle start
# krijgt bij het volgende cron-tick.

set -euo pipefail

REPO_DIR="${REPO_DIR:-$(dirname "$(dirname "$(readlink -f "$0")")")}"
BRANCH="${DEPLOY_BRANCH:-test}"
# DEPLOY_STACK wordt doorgegeven aan deploy.sh (test/acc). Default 'test'
# houdt bestaande cron werkend.
export DEPLOY_STACK="${DEPLOY_STACK:-test}"

cd "$REPO_DIR"

# Fetch de remote-branch stil (alleen wat we volgen).
git fetch --quiet origin "$BRANCH"

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse "origin/$BRANCH")"

if [ "$LOCAL" = "$REMOTE" ]; then
    # Niets nieuws — stil zijn zodat log-file bruikbaar blijft.
    exit 0
fi

echo "==> $(date -Is): nieuwe commit gedetecteerd op $BRANCH ($REMOTE)"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> deploy.sh starten"
bash "$REPO_DIR/scripts/deploy.sh"

echo "==> $(date -Is): auto-deploy klaar op $REMOTE"
