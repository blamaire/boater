# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Webapplicatie voor de Roei- en Zeilvereniging "Gouda" (RZVG). Laravel 12 / PHP 8.4 / MySQL 8. Nederlandstalig (`APP_LOCALE=nl`); UI-strings, doc-comments, exceptions en commit-berichten zijn allemaal Nederlands.

Het volledige ontwerp staat in [`docs/Ontwerpdocument-RZVG-concept.md`](docs/Ontwerpdocument-RZVG-concept.md) (38 hoofdstukken). Code-comments verwijzen vaak naar paragraafnummers (bijv. `§20.4`, `§26.4`) — dat is dat document. Raadpleeg het bij twijfel over bedoeling of scope.

## Werkomgeving — Docker-first

De app draait volledig in Docker; er is geen lokale PHP-, Composer- of Node-installatie nodig of verwacht. Voer **elk** PHP-/artisan-/composer-/npm-commando uit via `docker compose exec`, anders ontbreken extensies (`pdo_mysql`, `intl`, …) of klopt de DB-host (`db`) niet.

Services in `docker-compose.yml`: `app` (php-fpm), `web` (nginx, http://localhost:8000), `db` (mysql:8), `node` (vite dev-server op :5173). Bind-mount is de hele repo; bestanden die in de container ontstaan zijn root-owned op de Windows-host — dat is bewust (zie `docker/php/Dockerfile`).

Voor snelheid op Windows staan `vendor/`, `node_modules/`, `bootstrap/cache/` en `storage/framework/` in **named volumes** (leven binnen docker, niet op /mnt/c). Consequentie: de host ziet deze mappen leeg en `composer install` / `npm install` moet je in de container draaien.

```sh
docker compose up -d --build              # eerste keer
docker compose exec app composer install  # vult vendor-volume
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
| Lokale DB resetten + seeden | `composer db-reset` — draait `migrate:fresh --seed`. Gebruik dit altijd i.p.v. los `migrate:fresh`, anders heb je geen home-pagina en geen dev-users (zie `LocalDevUserSeeder`) en werkt inloggen niet. |
| Een user tot Beheerder maken (alle permissies) | `php artisan rzvg:make-admin <email>` — vereist bestaande user; maakt zo nodig een Person aan en koppelt de rol `Beheerder`. |

Tests draaien op een **in-memory SQLite** (`phpunit.xml`), dus PHPStan en Pest werken zonder dat de MySQL-container draait — alleen migraties tegen echte data hebben `db` nodig.

## Architectuur — high-level

Er zijn drie samenwerkende kerndiensten die alle domeinacties dragen:

**`App\Services\Proposals\ProposalEngine`** (§8 + §20) — Generieke goedkeuringsmotor. Elke wijziging die review behoeft wordt ingediend als `Proposal` (`subject_type` + `payload`). `submit()` kiest één van drie routes: (1) bypass-permissie → direct toepassen, (2) `auto_apply`-policy → direct toepassen, (3) reviewstappen aanmaken volgens een `ReviewPolicy`. Stappen worden goedgekeurd/afgewezen/teruggestuurd; bij de laatste goedkeuring volgt `applyApproved()` met apply-time hervalidatie (conflict → status `conflicted`, geen mutatie). Functiescheiding (indiener mag niet beslissen) en step-locking zitten in private guards. Wijzigingen per `subject_type` worden uitgevoerd door een `ProposalHandler` geregistreerd in `ProposalHandlerRegistry` (singleton, gebind in `AppServiceProvider`).

**`App\Services\Authorization\EffectivePermissions`** (§26.4) — De enige juiste manier om te vragen "mag deze persoon X?". Berekent de unie van rolpermissies (via actieve, niet-verlopen `role_assignments`) en directe `person_permissions`. `AppServiceProvider::boot()` koppelt dit aan Laravel's `Gate::before`, dus standaard `$user->can('permission.key')` werkt automatisch. **Niet bypassen** met losse role-checks of hardcoded ID's.

**`App\Services\Audit\AuditLogger`** (§31) — Elke domeinactie logt naar `audit_entries`. Entries zijn onveranderlijk: `PersonPermissionObserver` en `RoleAssignmentObserver` blokkeren updates/deletes op auditgevoelige modellen, en `AuditEntryImmutabilityTest` bewaakt dit. Voeg bij nieuwe domeinacties een `$audit->log(...)` toe binnen dezelfde transactie.

Model-laag (`app/Models/`): `Person` is de identiteit (apart van `User`); rollen, permissies, groepen en review-policies hangen daaromheen. Enums in `app/Enums/` zijn de bron voor statussen — gebruik die cases, geen losse strings.

## UI-patroon: CRUD-beheerschermen

Toegepast op de boekhouding-schermen (`DagboekBeheer`, `GrootboekBeheer`, `BtwCodeBeheer`, `ProductBeheer`); nieuwe of herbouwde admin-CRUD-schermen elders volgen hetzelfde patroon.

- **Actie-iconen, altijd uitgelijnd.** Acties in een tabel (bekijken/bewerken/verwijderen) zijn iconen via `<x-action-icon name="eye|pencil|trash|plus|check|xmark">`, elk gewrapt in `<x-action-cell icon="..." click="..." href="..." variant="primary|danger|success" confirm="...">`. Die component rendert altijd een `<td class="w-8 ...">`, ook als `icon` leeg blijft (bv. een prullenbak die voor een bepaalde rij niet van toepassing is) — zo staat dezelfde actie altijd op dezelfde horizontale plek, ongeacht welke acties een specifieke rij toont. Geen los `text-right whitespace-nowrap`-cellen met alle iconen door elkaar meer.
- **Eenvoudige tabellen (~2-4 velden): inline in de tabel, geen apart formulier erboven.** Een permanente "nieuw"-rij (gestippelde rand, `bg-gray-50/60`) onderaan de `tbody` met invoervelden die aan dezelfde Livewire-properties binden als bewerken; op de plek van de acties komt daar één `<x-action-icon name="plus">` in de laatste actie-kolom (leeg ervoor, net als de "laatste actie" op echte rijen — dus geen `colspan`+`text-center`, maar losse `w-8`-cellen zodat de plus exact onder de prullenbak-kolom uitlijnt). Bewerken gebeurt in-place: de rij zelf wisselt naar invoervelden, met `check`/`xmark` in plaats van `pencil`/`trash`. Voorbeeld: `DagboekBeheer`, de drie secties van `GrootboekBeheer`.
- **Uitgebreidere tabellen (meer velden, bv. incl. datumranges): een modal.** `<x-modal name="..." maxWidth="3xl">` (of groter — geen krappe veldjes, `maxWidth` ondersteunt t/m `4xl`) i.p.v. inline. Openen voor "nieuw" gebeurt puur client-side (`x-data=""` + `x-on:click="$dispatch('open-modal', 'naam')"` op de trigger-knop); openen voor "bewerken" gebeurt server-side vanuit de PHP `edit()`-methode ná het laden van het record (`$this->dispatch('open-modal', 'naam')`), zodat de velden al gevuld zijn zodra de modal verschijnt. Sluiten na succesvol opslaan: `$this->dispatch('close-modal', 'naam')` aan het eind van `save()`. Annuleren in de modal: `x-on:click="$dispatch('close')"`. Voorbeeld: `BtwCodeBeheer` (alle CRUD via de modal), `ProductBeheer` (alleen *aanmaken* via de modal — *bewerken* blijft een inline sectie omdat die ook de prijshistorie-workflow draagt, geen simpele save-en-sluit).
- **Consistente rij-hoogte.** Zowel databcellen als `<x-action-cell>` gebruiken `py-2` (niet `py-1.5`) — anders lopen de iconen niet verticaal gelijk met de tekst in dezelfde rij.
- Gedeelde velden die zowel in een inline-editrij/modal als in een apart bewerkscherm nodig zijn: een `@include('livewire.admin.partials.....')`-partial zonder props (deelt de scope van het ouder-Livewire-component) i.p.v. het formulier te dupliceren — zie `partials/product-fields.blade.php`.

## Conventies die het PR-proces afdwingt

- **Forward-only migraties.** Geen `down()` schrijven; corrigeer met een nieuwe migratie.
- **PHPStan level 5 dekt óók `tests/`** (Peststan-extension actief in `phpstan.neon.dist`). Geen ontsnappingsclausule voor testcode.
- **CI pijplijn** (`.github/workflows/ci.yml`) draait: Gitleaks (geheimen) → `composer audit` → `npm audit --audit-level=high` → Pint → PHPStan → Pest. CodeQL (`security-extended,security-and-quality`) draait apart op JS en GitHub Actions. Een falende `composer ci` lokaal = falende PR.
- **Geen credentials in `.env.example` of `docker-compose.yml`.** Lees waardes via `${VAR}`-substitutie uit een lokaal `.env` (die buiten git blijft). Config-bestanden gebruiken `env(...)` zónder dummy-fallback voor credentials (bv. `env('DB_USERNAME')`), zodat de app faalt bij ontbrekende env in plaats van stilletjes met `root`/`""` te draaien. Lokaal is er een pre-commit hook (`.githooks/pre-commit`, activeer met `git config core.hooksPath .githooks`) die Gitleaks over staged changes draait; CI doet hetzelfde over de hele repo.
- **Voortgang per fase** uit §34 staat in `memory/project_voortgang.md` — werk die bij na elke merged PR zodat een volgende sessie niet de hele git-log hoeft te herlezen.