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
test.rzvg.lamaire.nl  →  <ip-van-de-vserver>
```

TTL 300–3600. Wacht tot `dig test.rzvg.lamaire.nl` het juiste IP retourneert
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

Bij elke code-wijziging op `main`:

```sh
ssh rzvg@<server-ip>
cd /var/www/rzvg
bash scripts/deploy.sh
```

Idempotent — herhaalbaar zonder side-effects.

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

Minimaal aanbevolen (via cron als `rzvg`):

```sh
# Elke nacht om 3:00
0 3 * * * cd /var/www/rzvg && docker compose -f docker-compose.prod.yml exec -T db mysqldump -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > /home/rzvg/backups/db-$(date +\%F).sql.gz
```

Voor media synchroniseer je periodiek `/var/lib/docker/volumes/rzvg_media_data`
naar een tweede locatie (rsync, Backblaze B2, etc.).
