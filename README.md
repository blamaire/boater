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

De applicatie is bereikbaar op http://localhost:8080.

**Daarna:**

```sh
docker compose up -d
docker compose down
```
