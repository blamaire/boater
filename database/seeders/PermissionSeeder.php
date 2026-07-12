<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $entry) {
            Permission::updateOrCreate(
                ['key' => $entry['key']],
                [
                    'module' => $entry['module'],
                    'action' => $entry['action'],
                    'description' => $entry['description'] ?? $this->defaultDescription($entry['module'], $entry['action']),
                    'is_sensitive' => $entry['is_sensitive'] ?? false,
                ],
            );
        }
    }

    /**
     * Alleen permissies die daadwerkelijk worden afgedwongen — via een route/
     * view/component, als `bypass_permission` in een review-policy, of impliciet
     * via lidmaatschap. Rechten voor nog niet gebouwde modules worden bewust
     * niet vooruit geseed; ze komen terug zodra de bijbehorende feature er is.
     * Uitzondering: de governance-primitieven `impersonate` en `proposals.bypass`
     * (§8/§26) blijven gereserveerd.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        $entries = [];

        $modules = [
            // persons.update en memberships.approve worden als bypass_permission
            // in de review-policies gebruikt (zie ReviewPolicySeeder).
            'persons' => ['update'],
            'memberships' => ['approve'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'activities' => ['view', 'update'],
            'reservations' => ['view', 'create', 'update', 'approve'],
            'damage_reports' => ['view', 'create'],
            'pages' => ['view', 'create', 'update', 'publish', 'delete', 'push'],
            // Financiën — Fase 3. Productbeheer (producten, prijzen, rekening-
            // koppeling) valt onder één beheerpermissie.
            'products' => ['manage'],
            'audit_trail' => ['view'],
            'media' => ['view', 'upload', 'delete'],
            'queue' => ['manage'],
            'menu' => ['manage'],
            'site_settings' => ['manage'],
            'environments' => ['manage'],
            'users' => ['manage'],
            'reservable_objects' => ['manage'],
            'approver_groups' => ['manage'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $entries[] = [
                    'key' => "{$module}.{$action}",
                    'module' => $module,
                    'action' => $action,
                ];
            }
        }

        $entries[] = [
            'key' => 'impersonate',
            'module' => 'support',
            'action' => 'impersonate',
            'description' => 'Inloggen als andere gebruiker (zwaar gelogd)',
            'is_sensitive' => true,
        ];

        $entries[] = [
            'key' => 'proposals.bypass',
            'module' => 'proposals',
            'action' => 'bypass',
            'description' => 'Voorstellen direct doorvoeren zonder review',
            'is_sensitive' => true,
        ];

        // Een lid mag content-wijzigingen op CMS-pagina's voorstellen (§5, §26.4).
        // Wordt in EffectivePermissions automatisch verleend aan iedereen met
        // een actief lidmaatschap; hoeft dus niet op een rol te staan.
        $entries[] = [
            'key' => 'pages.propose',
            'module' => 'pages',
            'action' => 'propose',
            'description' => 'Een wijziging aan een CMS-pagina voorstellen (gaat via de goedkeuringsmotor)',
        ];

        return $entries;
    }

    private function defaultDescription(string $module, string $action): string
    {
        $moduleLabel = match ($module) {
            'persons' => 'Personen',
            'memberships' => 'Lidmaatschappen',
            'roles' => 'Rollen',
            'activities' => 'Activiteiten',
            'reservations' => 'Reserveringen',
            'damage_reports' => 'Schademeldingen',
            'pages' => 'Pagina\'s',
            'products' => 'Producten',
            'audit_trail' => 'Auditlogboek',
            'media' => 'Media',
            'queue' => 'Queue',
            'menu' => 'Publiek menu',
            'site_settings' => 'Site-instellingen',
            'environments' => 'Omgevingen',
            'users' => 'Gebruikers',
            'reservable_objects' => 'Objecten (reserveren)',
            'approver_groups' => 'Goedkeuringsgroepen',
            default => ucfirst($module),
        };

        return match ($action) {
            'view' => "{$moduleLabel} bekijken",
            'create' => "{$moduleLabel} aanmaken",
            'update' => "{$moduleLabel} wijzigen",
            'delete' => "{$moduleLabel} verwijderen",
            'publish' => "{$moduleLabel} publiceren",
            'approve' => "{$moduleLabel} goedkeuren",
            'push' => "{$moduleLabel} naar een andere omgeving pushen",
            'upload' => "{$moduleLabel} uploaden",
            'manage' => "{$moduleLabel} beheren",
            default => "{$action} op {$moduleLabel}",
        };
    }
}
