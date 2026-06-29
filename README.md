# RZVG-webapplicatie

Webapplicatie voor de Roei- en Zeilvereniging "Gouda" (RZVG).

Zie [`docs/Ontwerpdocument-RZVG-concept.md`](docs/Ontwerpdocument-RZVG-concept.md) voor het ontwerp.

## Stack

- PHP 8.2+ / Laravel 11
- MySQL 8
- Docker (lokale ontwikkelomgeving)

## Lokaal draaien

De applicatie draait volledig via Docker — geen lokale PHP- of Composer-installatie vereist.

```sh
docker compose up -d
```

De applicatie is dan bereikbaar op http://localhost:8080.
