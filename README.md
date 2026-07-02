# RZVG-webapplicatie

Webapplicatie voor de Roei- en Zeilvereniging "Gouda" (RZVG).

Zie [`docs/Ontwerpdocument-RZVG-concept.md`](docs/Ontwerpdocument-RZVG-concept.md) voor het ontwerp.

## Stack

- PHP 8.4 / Laravel 12
- MySQL 8
- Pest (testen)
- Docker (lokale ontwikkelomgeving)

## Lokaal draaien

De applicatie draait volledig via Docker — geen lokale PHP- of Composer-installatie vereist.

**Eerste keer:**

1. Kopieer `.env.example` naar `.env` en vul `DB_DATABASE`, `DB_USERNAME` en `DB_PASSWORD` in (vrij te kiezen — deze waarden worden ook door Docker gebruikt om de MySQL-container te initialiseren). Zonder deze env-variabelen weigert Laravel te booten — bewust, om te voorkomen dat we per ongeluk met defaults als `root`/`""` draaien.

2. Zet de git pre-commit hook aan zodat Gitleaks je diff scant op secrets vóór elke commit:

   ```sh
   git config core.hooksPath .githooks
   ```

   De hook draait `zricethezav/gitleaks` via Docker en blokkeert commits met credentials in de diff. In noodgevallen kun je 'm eenmalig omzeilen met `SKIP_GITLEAKS=1 git commit ...` — doe dat alleen na review.
3. Bouw en start de containers:

   ```sh
   docker compose up -d --build
   ```

4. Draai de database-migraties:

   ```sh
   docker compose exec app php artisan migrate
   ```

De applicatie is bereikbaar op http://localhost:8000.
Uitgaande mail (registratie-verificatie, herinneringen, …) vangt **Mailpit** af op http://localhost:8025 — geen mails verlaten je machine tijdens dev.

## Achtergrondtaken

Twee dedicated services draaien mee met `docker compose up -d`:

- **`queue`** — draait `php artisan queue:work database --tries=3 --sleep=3` en pakt gequeue'de jobs op (bv. registratie- en wachtwoord-reset-mails). Herstart met `docker compose restart queue` nadat je jobs-code hebt aangepast.
- **`scheduler`** — draait `php artisan schedule:work` en voert periodieke taken uit uit `app/Console/Kernel.php`.

Failed jobs zijn beheerbaar op `/beheer/failed-jobs` (permissie `queue.manage`): opnieuw uitvoeren of verwijderen.

**Daarna:**

```sh
docker compose up -d
docker compose down
```

> **Upgrade je bestaande `.env`?** Wijzig `MAIL_MAILER=log` naar `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` zoals in `.env.example` staat.
