<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // De rol "Lid" hoort in Fase 2 (Lidmaatschap), per lidmaatschapstype. Zie §6-7.
        Role::query()->where('name', 'Lid')->delete();
        Role::query()->where('name', 'Administrator')->delete();

        // "Beheerder" is de enige systeem-rol: naam, description en permissie-set
        // zijn vast, zodat er altijd een technische beheerdersrol bestaat.
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
