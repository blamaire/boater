<?php

use App\Livewire\Portal\MijnLidmaatschap;
use App\Models\AuditEntry;
use App\Models\Person;
use App\Models\PersonFieldVisibility;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Lid',
        'account_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

it('start met alle zichtbaarheden verborgen (privacy-first)', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->assertSet('visibility.email', false)
        ->assertSet('visibility.phone', false)
        ->assertSet('visibility.date_of_birth', false);
});

it('slaat een aan-toggle op als PersonFieldVisibility-record', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('toggleVisibility', 'email')
        ->assertSet('visibility.email', true);

    $record = PersonFieldVisibility::query()->where('person_id', $this->person->id)->where('field_key', 'email')->first();
    expect($record)->not->toBeNull();
    expect($record->visible_to_members)->toBeTrue();

    expect(AuditEntry::query()->where('action', 'person.field_visibility_updated')->count())->toBe(1);
});

it('een tweede toggle op hetzelfde veld werkt het bestaande record bij', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('toggleVisibility', 'phone')
        ->call('toggleVisibility', 'phone')
        ->assertSet('visibility.phone', false);

    expect(PersonFieldVisibility::query()->where('field_key', 'phone')->count())->toBe(1);
    expect(PersonFieldVisibility::query()->where('field_key', 'phone')->value('visible_to_members'))->toBeFalse();
});
