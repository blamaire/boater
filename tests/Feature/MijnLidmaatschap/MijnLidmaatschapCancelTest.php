<?php

use App\Enums\MembershipStatus;
use App\Livewire\Portal\MijnLidmaatschap;
use App\Models\AuditEntry;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Lid',
        'account_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

it('zegt een lopend actief lidmaatschap op met einddatum vandaag', function () {
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    $membership = Membership::create([
        'person_id' => $this->person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYear(),
    ]);

    Livewire::test(MijnLidmaatschap::class)
        ->call('cancelMembership')
        ->assertHasNoErrors();

    $membership->refresh();
    expect($membership->status)->toBe(MembershipStatus::Cancelled);
    expect($membership->end_date->toDateString())->toBe(now()->toDateString());

    expect(AuditEntry::query()->where('action', 'membership.cancelled')->count())->toBe(1);
});

it('meldt netjes dat er niets opgezegd kan worden zonder actief lidmaatschap', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('cancelMembership')
        ->assertHasNoErrors()
        ->assertSet('confirmCancelMembership', false);

    expect(AuditEntry::query()->where('action', 'membership.cancelled')->count())->toBe(0);
});
