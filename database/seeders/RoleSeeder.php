<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $lid = Role::updateOrCreate(
            ['name' => 'Lid'],
            [
                'description' => 'Standaardrol voor actieve leden',
                'is_system' => true,
            ],
        );

        $lidPermissions = Permission::query()
            ->whereIn('key', [
                'persons.search',
                'activities.view',
                'reservations.view',
                'reservations.create',
                'reservations.update',
                'reservations.delete',
                'damage_reports.view',
                'damage_reports.create',
                'damage_reports.update',
                'documents.view',
                'volunteer_tasks.view',
                'volunteer_tasks.sign_up',
            ])
            ->pluck('id');

        $lid->permissions()->sync($lidPermissions);

        Role::query()->where('name', 'Administrator')->delete();

        $beheerder = Role::updateOrCreate(
            ['name' => 'Beheerder'],
            [
                'description' => 'Alle permissies (technische beheerder)',
                'is_system' => true,
            ],
        );

        $beheerder->permissions()->sync(Permission::query()->pluck('id'));
    }
}
