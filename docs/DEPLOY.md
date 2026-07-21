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

## 2. Repo op de server + `.env.tst`

Elke omgeving heeft een eigen env-bestand naast de root van de repo:

- `.env.local` — dev (op je laptop)
- `.env.tst` — test-server
- `.env.acc` — acceptatie-server (zie §6.2)

De docker-compose stacks bind-mounten hun env-bestand als `/var/www/html/.env`
zodat Laravel altijd de juiste config leest.

```sh
# Als rzvg
sudo install -d -o rzvg -g rzvg /var/www
cd /var/www
git clone https://github.com/blamaire/boater.git rzvg-tst
cd rzvg-tst

cp .env.tst.example .env.tst
nano .env.tst   # vul minimaal APP_KEY, APP_DOMAIN, DB_PASSWORD,
                # DB_ROOT_PASSWORD, en MAIL_* in
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
cd /var/www/rzvg-tst
bash scripts/deploy.sh
```

Het script bouwt assets, containers, migreert de database, cache't config
en start alles. Na afloop moet de site live zijn op `https://APP_DOMAIN`.

## 5. Beheerder aanmaken

```sh
docker compose --env-file .env.tst -f docker-compose.prod.yml exec app \
    php artisan rzvg:make-admin <email>
```

## 6. Updates uitrollen

Bij elke code-wijziging op `main` (of welke branch je op de server hebt uitgechecked):

```sh
ssh rzvg@<server-ip>
cd /var/www/rzvg-tst
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
* * * * * flock -n /tmp/rzvg-auto-deploy.lock bash /var/www/rzvg-tst/scripts/auto-deploy.sh >> /var/log/rzvg-auto-deploy.log 2>&1
```

De `flock` voorkomt overlappende deploys. Standaard is `DEPLOY_BRANCH=test`;
zet er een `DEPLOY_BRANCH=<naam>=` in de cron voor als je een andere branch
wilt volgen.

Log realtime volgen:

```sh
tail -f /var/log/rzvg-auto-deploy.log
```

### 6.2 Acceptatie-omgeving op dezelfde VPS

De acceptatie-stack draait náást de test-stack. Eigen containers, eigen DB en
media, eigen subdomein — maar deelt de al draaiende Caddy voor HTTPS.

Voorwaarden vóór je begint:
- Test-omgeving werkt en Caddy draait al op poort 80/443.
- DNS: `rzvg-acc.lamaire.nl` wijst naar dezelfde IP als de test-server.

#### Stap 1 — Eenmalige host-setup

```sh
# Als rzvg (SSH)
sudo install -d -o rzvg -g rzvg /var/www/rzvg-acc

# Deel-netwerk zodat de test-caddy de acc-app-container kan bereiken.
docker network create rzvg_shared

# Repo klonen op de acc-locatie
git clone https://github.com/blamaire/boater.git /var/www/rzvg-acc
cd /var/www/rzvg-acc
git checkout acceptatie
```

#### Stap 2 — `.env.acc` voor acc

```sh
cp .env.acc.example .env.acc
nano .env.acc   # vul APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, RZVG_IMPORT_TOKEN
```

Genereer APP_KEY en `RZVG_IMPORT_TOKEN` op dezelfde manier als bij test (zie
stap 2 hierboven). Kies andere waardes dan test — deze omgevingen delen niets.
`ACC_DOMAIN=rzvg-acc.lamaire.nl` staat al in het template; niet in
`.env.tst` zetten. De test-caddy leest `.env.acc` optioneel in en pikt de
`ACC_DOMAIN` daaruit op.

#### Stap 3 — Caddy uit test-stack herstarten

Zodat de Caddy de nieuwe env-mount en Caddyfile-uitbreiding oppikt:

```sh
cd /var/www/rzvg-tst
docker compose --env-file .env.tst -f docker-compose.prod.yml up -d --force-recreate caddy
```

#### Stap 4 — Acc-stack starten

```sh
cd /var/www/rzvg-acc
DEPLOY_STACK=acc bash scripts/deploy.sh
```

Dit bouwt de acc-containers, migreert de acc-DB en seedt permissies +
categorieën. Na afloop moet `https://rzvg-acc.lamaire.nl` een verse
verwelkomingspagina tonen (nog geen users).

#### Stap 5 — Beheerder aanmaken op acc

```sh
docker compose --env-file .env.acc -f docker-compose.acc.yml exec app \
    php artisan rzvg:make-admin <email>
```

#### Stap 6 — Auto-deploy voor acc

`/var/log` is root-owned; zonder onderstaande `touch`/`chown` kan `rzvg` het logbestand niet aanmaken en faalt de hele cron-regel **stil** (geen lock-file, geen log, geen foutmelding — er is geen MTA geïnstalleerd, dus cron gooit de output gewoon weg):

```sh
sudo touch /var/log/rzvg-acc-deploy.log && sudo chown rzvg:rzvg /var/log/rzvg-acc-deploy.log
```

Voeg daarna een tweede cron-regel toe aan `crontab -e` (als `rzvg`):

```
* * * * * DEPLOY_STACK=acc DEPLOY_BRANCH=acceptatie REPO_DIR=/var/www/rzvg-acc flock -n /tmp/rzvg-acc-deploy.lock bash /var/www/rzvg-acc/scripts/auto-deploy.sh >> /var/log/rzvg-acc-deploy.log 2>&1
```

Aparte lockfile en logfile zodat test en acc niet met elkaar botsen.

---

## Troubleshooting

- **Caddy krijgt geen certificaat**: controleer DNS-resolutie én dat poort 80
  en 443 open zijn (`sudo ufw status`).
- **Migratie faalt op ontbrekende env-vars**: `.env` moet aan de root van de
  repo staan; niet in een subdirectory.
- **Assets niet zichtbaar**: `public/build/` moet bestaan; herbouw met
  `docker run --rm -v "$PWD":/app -w /app node:22-alpine sh -c 'npm ci && npm run build'`.
- **Queue-jobs draaien niet**: `docker compose --env-file .env.tst -f docker-compose.prod.yml logs queue`.
  Voor code-wijzigingen: het script draait automatisch `queue:restart`.
- **`rzvg-acc.lamaire.nl` geeft 502 / bad gateway**: caddy kan de acc-app-container
  niet vinden. Controleer met `docker network inspect rzvg_shared` of zowel
  `rzvg-caddy` als `rzvg-acc-app` in het netwerk zitten. Zo niet: `docker network
  create rzvg_shared` en beide stacks herstarten (`up -d`).
- **Acc-thumbnails 404 na eerste upload**: caddy heeft `rzvg_acc_media_data`
  nog niet gezien. Herstart caddy met `docker compose --env-file .env.tst -f docker-compose.prod.yml
  up -d --force-recreate caddy`.

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
0 3 * * * bash /var/www/rzvg-tst/scripts/backup.sh >> /var/log/rzvg-backup.log 2>&1
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
