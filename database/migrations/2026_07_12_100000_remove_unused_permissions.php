<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verwijdert permissies voor nog niet gebouwde modules en ongebruikte
 * lifecycle-acties, zodat er geen dood recht in de catalogus achterblijft.
 * Zie {@see PermissionSeeder} voor de rechten die blijven.
 *
 * `role_permissions` en `person_permissions` hebben `cascadeOnDelete` op
 * `permission_id`, dus gekoppelde toewijzingen verdwijnen automatisch mee.
 * De audit_entries van eerdere toekenningen blijven (append-only, apart).
 *
 * Behouden ondanks (nog) geen gebruik: de governance-primitieven
 * `impersonate` en `proposals.bypass` (§8/§26).
 */
return new class extends Migration
{
    public function up(): void
    {
        $keys = [
            // Nog niet gebouwde modules.
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'ledger.view', 'ledger.update',
            'mailings.view', 'mailings.create', 'mailings.send',
            'documents.view', 'documents.create', 'documents.update', 'documents.delete',
            'imports.view', 'imports.create', 'imports.run',
            'volunteer_tasks.view', 'volunteer_tasks.create', 'volunteer_tasks.update', 'volunteer_tasks.delete', 'volunteer_tasks.sign_up',
            'communication_log.view', 'communication_log.create',
            'review_settings.update',
            'ice_contacts.view',
            // Ongebouwde persoon-/lidmaatschapsadmin (persons.update en
            // memberships.approve blijven: bypass_permission in review-policies).
            'persons.view', 'persons.create', 'persons.delete', 'persons.search',
            'memberships.view', 'memberships.create', 'memberships.update', 'memberships.delete',
            // Ongebruikte lifecycle-acties van gebouwde modules.
            'activities.create', 'activities.publish', 'activities.approve', 'activities.delete',
            'reservations.delete',
            'damage_reports.update', 'damage_reports.resolve',
            'pages.approve',
        ];

        DB::table('permissions')->whereIn('key', $keys)->delete();
    }
};
