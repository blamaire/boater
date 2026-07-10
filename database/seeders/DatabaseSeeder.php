<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TemplateSeeder::class,
            HomeSystemPageSeeder::class,
            // ApproverGroupSeeder móét vóór ReviewPolicySeeder draaien:
            // policies verwijzen naar deze groepen als assignee.
            ApproverGroupSeeder::class,
            ReviewPolicySeeder::class,
            MembershipTypeSeeder::class,
            ActivityCategorySeeder::class,
            // Alleen actief bij APP_ENV=local (interne guard); seed nooit
            // productie-tokens of test-users op andere omgevingen.
            LocalDevUserSeeder::class,
        ]);
    }
}
