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
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        $entries = [];

        $crud = ['view', 'create', 'update', 'delete'];
        $contentLifecycle = ['view', 'create', 'update', 'publish', 'approve', 'delete'];
        $proposalLifecycle = ['view', 'create', 'update', 'approve', 'delete'];

        $modules = [
            'persons' => $crud,
            'memberships' => array_merge($crud, ['approve']),
            'roles' => $crud,
            'activities' => $contentLifecycle,
            'reservations' => $proposalLifecycle,
            'damage_reports' => ['view', 'create', 'update', 'resolve'],
            'pages' => $contentLifecycle,
            'invoices' => $crud,
            'ledger' => ['view', 'update'],
            'mailings' => ['view', 'create', 'send'],
            'documents' => $crud,
            'imports' => ['view', 'create', 'run'],
            'volunteer_tasks' => array_merge($crud, ['sign_up']),
            'communication_log' => ['view', 'create'],
            'audit_trail' => ['view'],
            'review_settings' => ['update'],
            'media' => ['view', 'upload', 'delete'],
            'queue' => ['manage'],
            'menu' => ['manage'],
            'ice_contacts' => ['view'],
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
            'key' => 'persons.search',
            'module' => 'persons',
            'action' => 'search',
            'description' => 'Andere leden opzoeken (Leden zoeken)',
        ];

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
            'invoices' => 'Facturen',
            'ledger' => 'Grootboek',
            'mailings' => 'Mailings',
            'documents' => 'Documenten',
            'imports' => 'Imports',
            'volunteer_tasks' => 'Vrijwilligerstaken',
            'communication_log' => 'Communicatielogboek',
            'audit_trail' => 'Auditlogboek',
            'review_settings' => 'Reviewinstellingen',
            'media' => 'Media',
            'queue' => 'Queue',
            'menu' => 'Publiek menu',
            'ice_contacts' => 'ICE-contacten',
            default => ucfirst($module),
        };

        return match ($action) {
            'view' => "{$moduleLabel} bekijken",
            'create' => "{$moduleLabel} aanmaken",
            'update' => "{$moduleLabel} wijzigen",
            'delete' => "{$moduleLabel} verwijderen",
            'publish' => "{$moduleLabel} publiceren",
            'approve' => "{$moduleLabel} goedkeuren",
            'resolve' => "{$moduleLabel} afhandelen",
            'send' => "{$moduleLabel} versturen",
            'run' => "{$moduleLabel} uitvoeren",
            'sign_up' => "Inschrijven op {$moduleLabel}",
            'upload' => "{$moduleLabel} uploaden",
            'manage' => "{$moduleLabel} beheren (failed jobs opnieuw uitvoeren of verwijderen)",
            default => "{$action} op {$moduleLabel}",
        };
    }
}
