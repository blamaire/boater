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
                    'description' => $entry['description'] ?? null,
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
}
