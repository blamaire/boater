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

1. Kopieer `.env.example` naar `.env` en vul `DB_DATABASE`, `DB_USERNAME` en `DB_PASSWORD` in (vrij te kiezen — deze waarden worden ook door Docker gebruikt om de MySQL-container te initialiseren).
2. Bouw en start de containers:

   ```sh
   docker compose up -d --build
   ```

3. Draai de database-migraties:

   ```sh
   docker compose exec app php artisan migrate
   ```

De applicatie is bereikbaar op http://localhost:8000.
Uitgaande mail (registratie-verificatie, herinneringen, …) vangt **Mailpit** af op http://localhost:8025 — geen mails verlaten je machine tijdens dev.

**Daarna:**

```sh
docker compose up -d
docker compose down
```

> **Upgrade je bestaande `.env`?** Wijzig `MAIL_MAILER=log` naar `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` zoals in `.env.example` staat.
