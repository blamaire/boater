<?php

use App\Livewire\Portal\MijnLidmaatschap;
use App\Models\AuditEntry;
use App\Models\IceContact;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

it('maakt een nieuw ICE-contact aan en logt naar audit', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('openIceForm')
        ->set('iceForm.name', 'Piet Partner')
        ->set('iceForm.relation', 'partner')
        ->set('iceForm.phone', '0611223344')
        ->call('saveIceContact')
        ->assertHasNoErrors();

    $contact = IceContact::query()->where('person_id', $this->person->id)->first();
    expect($contact)->not->toBeNull();
    expect($contact->name)->toBe('Piet Partner');
    expect($contact->relation)->toBe('partner');

    expect(AuditEntry::query()->where('action', 'ice_contact.created')->count())->toBe(1);
});

it('wijzigt een bestaand ICE-contact en logt naar audit', function () {
    $contact = IceContact::create([
        'person_id' => $this->person->id,
        'name' => 'Piet Partner',
        'relation' => 'partner',
        'phone' => '0611223344',
    ]);

    Livewire::test(MijnLidmaatschap::class)
        ->call('openIceForm', $contact->id)
        ->set('iceForm.phone', '0611111111')
        ->call('saveIceContact')
        ->assertHasNoErrors();

    expect($contact->refresh()->phone)->toBe('0611111111');
    expect(AuditEntry::query()->where('action', 'ice_contact.updated')->count())->toBe(1);
});

it('verwijdert een ICE-contact en logt naar audit', function () {
    $contact = IceContact::create([
        'person_id' => $this->person->id,
        'name' => 'Piet',
        'relation' => 'vader',
        'phone' => '0612345678',
    ]);

    Livewire::test(MijnLidmaatschap::class)
        ->call('deleteIceContact', $contact->id)
        ->assertHasNoErrors();

    expect(IceContact::query()->find($contact->id))->toBeNull();
    expect(AuditEntry::query()->where('action', 'ice_contact.deleted')->count())->toBe(1);
});

it('valideert verplichte velden bij aanmaken', function () {
    Livewire::test(MijnLidmaatschap::class)
        ->call('openIceForm')
        ->call('saveIceContact')
        ->assertHasErrors(['iceForm.name', 'iceForm.relation', 'iceForm.phone']);
});

it('weigert het bewerken van een ICE-contact van iemand anders', function () {
    $andereUser = User::factory()->create(['email_verified_at' => now()]);
    $andere = Person::create([
        'first_name' => 'Ander',
        'last_name' => 'Lid',
        'account_id' => $andereUser->id,
    ]);
    $contactVanAnder = IceContact::create([
        'person_id' => $andere->id,
        'name' => 'X',
        'relation' => 'partner',
        'phone' => '0000',
    ]);

    // openIceForm zoekt via person_id => id van huidig lid; ander lid → not found.
    expect(fn () => Livewire::test(MijnLidmaatschap::class)->call('openIceForm', $contactVanAnder->id))
        ->toThrow(ModelNotFoundException::class);

    // Contact bestaat nog steeds; niks van iemand anders is aangeraakt.
    expect(IceContact::query()->find($contactVanAnder->id))->not->toBeNull();
});
