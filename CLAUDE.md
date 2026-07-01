# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Webapplicatie voor de Roei- en Zeilvereniging "Gouda" (RZVG). Laravel 12 / PHP 8.4 / MySQL 8. Nederlandstalig (`APP_LOCALE=nl`); UI-strings, doc-comments, exceptions en commit-berichten zijn allemaal Nederlands.

Het volledige ontwerp staat in [`docs/Ontwerpdocument-RZVG-concept.md`](docs/Ontwerpdocument-RZVG-concept.md) (38 hoofdstukken). Code-comments verwijzen vaak naar paragraafnummers (bijv. `§20.4`, `§26.4`) — dat is dat document. Raadpleeg het bij twijfel over bedoeling of scope.

## Werkomgeving — Docker-first

De app draait volledig in Docker; er is geen lokale PHP-, Composer- of Node-installatie nodig of verwacht. Voer **elk** PHP-/artisan-/composer-/npm-commando uit via `docker compose exec`, anders ontbreken extensies (`pdo_mysql`, `intl`, …) of klopt de DB-host (`db`) niet.

Services in `docker-compose.yml`: `app` (php-fpm), `web` (nginx, http://localhost:8000), `db` (mysql:8), `node` (vite dev-server op :5173). Bind-mount is de hele repo; bestanden die in de container ontstaan zijn root-owned op de Windows-host — dat is bewust (zie `docker/php/Dockerfile`).

```sh
docker compose up -d --build              # eerste keer
docker compose exec app php artisan migrate
docker compose up -d / docker compose down
```

## Veelgebruikte commando's

Alles via `docker compose exec app …`:

| Doel | Commando |
| --- | --- |
| Volledige CI lokaal (lint + stan + test) | `composer ci` |
| Codestijl checken / fixen | `vendor/bin/pint --test` / `vendor/bin/pint` |
| Statische analyse | `vendor/bin/phpstan analyse --memory-limit=512M` |
| Hele testsuite | `vendor/bin/pest` of `php artisan test` |
| Eén testbestand | `vendor/bin/pest tests/Feature/Proposals/ProposalEngineTest.php` |
| Eén test op naam | `vendor/bin/pest --filter="approves stap voor stap"` |
| Migrate (lokaal MySQL) | `php artisan migrate` |
| Een user tot Beheerder maken (alle permissies) | `php artisan rzvg:make-admin <email>` — vereist bestaande user; maakt zo nodig een Person aan en koppelt de rol `Beheerder`. |

Tests draaien op een **in-memory SQLite** (`phpunit.xml`), dus PHPStan en Pest werken zonder dat de MySQL-container draait — alleen migraties tegen echte data hebben `db` nodig.

## Architectuur — high-level

Er zijn drie samenwerkende kerndiensten die alle domeinacties dragen:

**`App\Services\Proposals\ProposalEngine`** (§8 + §20) — Generieke goedkeuringsmotor. Elke wijziging die review behoeft wordt ingediend als `Proposal` (`subject_type` + `payload`). `submit()` kiest één van drie routes: (1) bypass-permissie → direct toepassen, (2) `auto_apply`-policy → direct toepassen, (3) reviewstappen aanmaken volgens een `ReviewPolicy`. Stappen worden goedgekeurd/afgewezen/teruggestuurd; bij de laatste goedkeuring volgt `applyApproved()` met apply-time hervalidatie (conflict → status `conflicted`, geen mutatie). Functiescheiding (indiener mag niet beslissen) en step-locking zitten in private guards. Wijzigingen per `subject_type` worden uitgevoerd door een `ProposalHandler` geregistreerd in `ProposalHandlerRegistry` (singleton, gebind in `AppServiceProvider`).

**`App\Services\Authorization\EffectivePermissions`** (§26.4) — De enige juiste manier om te vragen "mag deze persoon X?". Berekent de unie van rolpermissies (via actieve, niet-verlopen `role_assignments`) en directe `person_permissions`. `AppServiceProvider::boot()` koppelt dit aan Laravel's `Gate::before`, dus standaard `$user->can('permission.key')` werkt automatisch. **Niet bypassen** met losse role-checks of hardcoded ID's.

**`App\Services\Audit\AuditLogger`** (§31) — Elke domeinactie logt naar `audit_entries`. Entries zijn onveranderlijk: `PersonPermissionObserver` en `RoleAssignmentObserver` blokkeren updates/deletes op auditgevoelige modellen, en `AuditEntryImmutabilityTest` bewaakt dit. Voeg bij nieuwe domeinacties een `$audit->log(...)` toe binnen dezelfde transactie.

Model-laag (`app/Models/`): `Person` is de identiteit (apart van `User`); rollen, permissies, groepen en review-policies hangen daaromheen. Enums in `app/Enums/` zijn de bron voor statussen — gebruik die cases, geen losse strings.

## Conventies die het PR-proces afdwingt

- **Forward-only migraties.** Geen `down()` schrijven; corrigeer met een nieuwe migratie.
- **PHPStan level 5 dekt óók `tests/`** (Peststan-extension actief in `phpstan.neon.dist`). Geen ontsnappingsclausule voor testcode.
- **CI pijplijn** (`.github/workflows/ci.yml`) draait: Gitleaks (geheimen) → `composer audit` → `npm audit --audit-level=high` → Pint → PHPStan → Pest. CodeQL (`security-extended,security-and-quality`) draait apart op JS en GitHub Actions. Een falende `composer ci` lokaal = falende PR.
- **Geen credentials in `.env.example` of `docker-compose.yml`.** Lees waardes via `${VAR}`-substitutie uit een lokaal `.env` (die buiten git blijft).
- **Voortgang per fase** uit §34 staat in `memory/project_voortgang.md` — werk die bij na elke merged PR zodat een volgende sessie niet de hele git-log hoeft te herlezen.