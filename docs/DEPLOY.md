# Deploy naar de test-server

Deze gids beschrijft hoe je een schone Strato-V-Server (of vergelijkbare
Ubuntu-24.04-VPS) klaarmaakt en de RZVG-app erop laat draaien.

Werkflow: **provision → configureren → deployen → updaten**.

---

## 1. Server-provision (éénmalig)

Vereist: een verse Ubuntu 24.04 V-Server met SSH-key voor `root`.

```sh
# Login als root
ssh root@<server-ip>

# Repo klonen op tijdelijke plek (voor provision-script)
apt update && apt install -y git
git clone https://github.com/blamaire/boater.git /tmp/rzvg
bash /tmp/rzvg/scripts/provision-server.sh
```

Dit installeert Docker, UFW, fail2ban, automatische updates en de deploy-user
`rzvg`. Root-SSH-login wordt aan het eind uitgeschakeld — vanaf dan log je
in als `rzvg`.

Vervolg vanaf je lokale machine:

```sh
ssh rzvg@<server-ip>
```

## 2. Repo op de server + `.env`

```sh
# Als rzvg
sudo install -d -o rzvg -g rzvg /var/www
cd /var/www
git clone https://github.com/blamaire/boater.git rzvg
cd rzvg

cp .env.production.example .env
nano .env   # vul minimaal APP_KEY, APP_DOMAIN, DB_PASSWORD, DB_ROOT_PASSWORD,
            # en MAIL_* in
```

Genereer een APP_KEY:

```sh
docker run --rm -v "$PWD":/app -w /app php:8.4-cli php -r \
    "require 'vendor/autoload.php'; echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```
(of via `php artisan key:generate --show` nadat de container draait.)

## 3. DNS

Zet in het DNS-paneel van je domein een **A-record**:

```
rzvg-tst.lamaire.nl  →  <ip-van-de-vserver>
```

TTL 300–3600. Wacht tot `dig rzvg-tst.lamaire.nl` het juiste IP retourneert
(meestal binnen enkele minuten). Caddy heeft een geldige DNS-resolutie nodig
om het Let's Encrypt-certificaat op te halen.

## 4. Eerste deploy

```sh
cd /var/www/rzvg
bash scripts/deploy.sh
```

Het script bouwt assets, containers, migreert de database, cache't config
en start alles. Na afloop moet de site live zijn op `https://APP_DOMAIN`.

## 5. Beheerder aanmaken

```sh
docker compose -f docker-compose.prod.yml exec app php artisan rzvg:make-admin <email>
```

## 6. Updates uitrollen

Bij elke code-wijziging op `main` (of welke branch je op de server hebt uitgechecked):

```sh
ssh rzvg@<server-ip>
cd /var/www/rzvg
bash scripts/deploy.sh
```

Idempotent — herhaalbaar zonder side-effects.

### 6.1 Auto-deploy vanaf een `test`-branch

Zodra je merges naar de branch `test` automatisch wilt uitrollen, activeer
je de watcher-cron. Op de server als `rzvg`:

```sh
sudo touch /var/log/rzvg-auto-deploy.log && sudo chown rzvg:rzvg /var/log/rzvg-auto-deploy.log
crontab -e
```

Voeg toe:

```
# Elke minuut kijken of origin/test nieuwe commits heeft en deployen.
* * * * * flock -n /tmp/rzvg-auto-deploy.lock bash /var/www/rzvg/scripts/auto-deploy.sh >> /var/log/rzvg-auto-deploy.log 2>&1
```

De `flock` voorkomt overlappende deploys. Standaard is `DEPLOY_BRANCH=test`;
zet er een `DEPLOY_BRANCH=<naam>=` in de cron voor als je een andere branch
wilt volgen.

Log realtime volgen:

```sh
tail -f /var/log/rzvg-auto-deploy.log
```

---

## Troubleshooting

- **Caddy krijgt geen certificaat**: controleer DNS-resolutie én dat poort 80
  en 443 open zijn (`sudo ufw status`).
- **Migratie faalt op ontbrekende env-vars**: `.env` moet aan de root van de
  repo staan; niet in een subdirectory.
- **Assets niet zichtbaar**: `public/build/` moet bestaan; herbouw met
  `docker run --rm -v "$PWD":/app -w /app node:22-alpine sh -c 'npm ci && npm run build'`.
- **Queue-jobs draaien niet**: `docker compose -f docker-compose.prod.yml logs queue`.
  Voor code-wijzigingen: het script draait automatisch `queue:restart`.

## Backup

`scripts/backup.sh` dumpt MySQL + media naar `/home/rzvg/backups`, met
standaard 30 dagen retention. Activeren via cron (als `rzvg`):

```sh
mkdir -p /home/rzvg/backups
sudo touch /var/log/rzvg-backup.log && sudo chown rzvg:rzvg /var/log/rzvg-backup.log
crontab -e
```

Voeg toe:

```
# Elke nacht om 03:00 een backup + retention-cleanup.
0 3 * * * bash /var/www/rzvg/scripts/backup.sh >> /var/log/rzvg-backup.log 2>&1
```

Bestanden die het aanmaakt:

- `/home/rzvg/backups/db-YYYY-MM-DD-HHMM.sql.gz` — gzipped mysqldump
- `/home/rzvg/backups/media-YYYY-MM-DD-HHMM.tar.gz` — tar van het media-volume

### Off-site kopie (aanbevolen)

Voor bescherming tegen server-verlies dupliceer je de backups naar een
andere locatie (rsync, S3-compatible, Backblaze B2). Voorbeeld met rsync
naar een tweede host:

```
15 3 * * * rsync -aq --delete /home/rzvg/backups/ backup-user@backup-host:/rzvg/
```
