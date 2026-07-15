<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Livewire\Admin\Auditlogboek;
use App\Models\AuditEntry;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist audit_trail.view permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/auditlogboek')->assertForbidden();
});

it('rendert de pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/auditlogboek')->assertOk()->assertSee('Auditlogboek');
});

it('toont auditregels in het overzicht', function () {
    AuditEntry::create(['action' => 'role.assigned', 'occurred_at' => Carbon::now()]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->assertSee('role.assigned');
});

it('filtert op module via de action-prefix', function () {
    AuditEntry::create(['action' => 'role.assigned', 'occurred_at' => Carbon::now()]);
    AuditEntry::create(['action' => 'reservation.created', 'occurred_at' => Carbon::now()]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->set('module', 'role')
        ->assertSee('role.assigned')
        ->assertDontSee('reservation.created');
});

it('filtert op vrije zoekterm', function () {
    AuditEntry::create(['action' => 'menu.item_added', 'occurred_at' => Carbon::now()]);
    AuditEntry::create(['action' => 'environment.created', 'occurred_at' => Carbon::now()]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->set('search', 'environment')
        ->assertSee('environment.created')
        ->assertDontSee('menu.item_added');
});

it('filtert op periode', function () {
    // Acties die niet in de UI-placeholder ("bijv. role.assigned") voorkomen,
    // zodat assertDontSee alleen de tabelinhoud toetst.
    AuditEntry::create(['action' => 'activity.created', 'occurred_at' => Carbon::parse('2026-01-10 12:00:00')]);
    AuditEntry::create(['action' => 'reservation.created', 'occurred_at' => Carbon::parse('2026-06-10 12:00:00')]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->set('dateFrom', '2026-05-01')
        ->assertSee('reservation.created')
        ->assertDontSee('activity.created');
});

it('maakt een subject met detailpagina klikbaar', function () {
    $proposal = Proposal::create([
        'subject_type' => Person::class,
        'subject_id' => 1,
        'change_type' => ChangeType::Update,
        'payload' => [],
        'status' => ProposalStatus::Applied,
        'current_step' => 0,
    ]);
    AuditEntry::create([
        'action' => 'proposal.applied',
        'subject_type' => Proposal::class,
        'subject_id' => $proposal->id,
        'occurred_at' => Carbon::now(),
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->assertSee(route('admin.proposals.show', $proposal->id), false);
});

it('toont een veld-diff in de detailweergave', function () {
    $entry = AuditEntry::create([
        'action' => 'person.field_updated',
        'before' => ['city' => 'Gouda'],
        'after' => ['city' => 'Utrecht'],
        'occurred_at' => Carbon::now(),
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(Auditlogboek::class)
        ->call('show', $entry->id)
        ->assertSee('city')
        ->assertSee('Gouda')
        ->assertSee('Utrecht');
});
