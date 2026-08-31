<?php

use App\Livewire\Admin\MessageTemplateBeheer;
use App\Models\MessageTemplate;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist message_templates.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/berichtsjablonen')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/berichtsjablonen')->assertOk()->assertSee('Berichtsjablonen');
});

it('maakt een nieuw sjabloon aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->set('key', 'welkomstmail')
        ->set('name', 'Welkomstmail')
        ->set('subject', 'Welkom!')
        ->set('body', '<p>Hallo {{voornaam}}</p>')
        ->set('type', 'transactioneel')
        ->call('save')
        ->assertHasNoErrors();

    $template = MessageTemplate::query()->where('key', 'welkomstmail')->firstOrFail();
    expect($template->name)->toBe('Welkomstmail')
        ->and($template->subject)->toBe('Welkom!');
});

it('bewerkt onderwerp en inhoud van een bestaand sjabloon zonder de sleutel te wijzigen', function () {
    $template = MessageTemplate::create([
        'key' => 'bestaand',
        'name' => 'Bestaand',
        'subject' => 'Oud onderwerp',
        'body' => '<p>Oud</p>',
        'type' => 'transactioneel',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('edit', $template->id)
        ->set('key', 'andere-sleutel')
        ->set('subject', 'Nieuw onderwerp')
        ->set('body', '<p>Nieuw</p>')
        ->call('save')
        ->assertHasErrors('key');

    expect($template->fresh()->key)->toBe('bestaand')
        ->and($template->fresh()->subject)->toBe('Oud onderwerp');
});

it('bepaalt de beschikbare variabelen systeemseitig uit de registry, niet uit gebruikersinvoer', function () {
    $template = MessageTemplate::create([
        'key' => 'enrollment_confirmed',
        'name' => 'Inschrijfbevestiging (bevestigd)',
        'subject' => 'X',
        'body' => '<p>X</p>',
        'type' => 'transactioneel',
    ]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(MessageTemplateBeheer::class)
        ->call('edit', $template->id);

    expect($component->get('availableVariables'))->toBe(['voornaam', 'achternaam', 'titel', 'datum', 'locatie_regel', 'actie_knop']);

    // Een sleutel zonder eigen trigger heeft geen bekende variabelen.
    $component->set('key', 'onbekende_sleutel_zonder_trigger');
    expect($component->get('availableVariables'))->toBe([]);
});

it('weigert een dubbele sleutel bij aanmaken', function () {
    MessageTemplate::create([
        'key' => 'dubbel',
        'name' => 'Een',
        'subject' => 'X',
        'body' => '<p>X</p>',
        'type' => 'transactioneel',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->set('key', 'dubbel')
        ->set('name', 'Twee')
        ->set('subject', 'Y')
        ->set('body', '<p>Y</p>')
        ->set('type', 'transactioneel')
        ->call('save')
        ->assertHasErrors('key');
});
