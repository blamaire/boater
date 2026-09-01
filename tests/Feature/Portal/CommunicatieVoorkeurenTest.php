<?php

use App\Livewire\Portal\CommunicatieVoorkeuren;
use App\Models\AuditEntry;
use App\Models\CommunicationPreference;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier', 'account_id' => $this->user->id]);
});

it('vereist inloggen', function () {
    $this->get('/mijn/communicatievoorkeuren')->assertRedirect('/login');
});

it('rendert de voorkeurenpagina met de nieuwsbrief uitgeschakeld als er nog geen voorkeur bestaat', function () {
    $this->actingAs($this->user)
        ->get('/mijn/communicatievoorkeuren')
        ->assertOk()
        ->assertSee('Nieuwsbrief');
});

it('schakelt een voorkeur in en slaat hem op', function () {
    $this->actingAs($this->user);

    Livewire::test(CommunicatieVoorkeuren::class)
        ->assertSet('preferences.nieuwsbrief', false)
        ->call('toggle', 'nieuwsbrief')
        ->assertSet('preferences.nieuwsbrief', true);

    $preference = CommunicationPreference::query()->where('person_id', $this->person->id)->where('category', 'nieuwsbrief')->firstOrFail();
    expect($preference->opted_in)->toBeTrue();
    expect(AuditEntry::query()->where('action', 'communication_preference.updated')->exists())->toBeTrue();
});

it('schakelt een ingeschakelde voorkeur weer uit', function () {
    CommunicationPreference::create(['person_id' => $this->person->id, 'category' => 'nieuwsbrief', 'opted_in' => true]);

    $this->actingAs($this->user);

    Livewire::test(CommunicatieVoorkeuren::class)
        ->assertSet('preferences.nieuwsbrief', true)
        ->call('toggle', 'nieuwsbrief')
        ->assertSet('preferences.nieuwsbrief', false);

    expect(CommunicationPreference::query()->where('person_id', $this->person->id)->where('category', 'nieuwsbrief')->value('opted_in'))->toBeFalsy();
});

it('weigert een onbekende categorie', function () {
    $this->actingAs($this->user);

    Livewire::test(CommunicatieVoorkeuren::class)->call('toggle', 'onbekend')->assertStatus(422);
});
