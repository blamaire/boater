<?php

use App\Livewire\Admin\MessageTemplateBeheer;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateFolder;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MessageTemplateFolderSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MessageTemplateFolderSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->systeemberichten = MessageTemplateFolder::query()->where('name', 'Systeemberichten')->firstOrFail();
    $this->mailings = MessageTemplateFolder::query()->where('name', 'Mailings')->firstOrFail();
});

it('vereist message_templates.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/berichtsjablonen')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder met de twee root-mappen', function () {
    $this->actingAs($this->beheerder)->get('/beheer/berichtsjablonen')
        ->assertOk()
        ->assertSee('Berichtsjablonen')
        ->assertSee('Systeemberichten')
        ->assertSee('Mailings');
});

it('navigeert een map in en weer terug naar de root', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $this->mailings->id)
        ->assertSet('currentFolderId', $this->mailings->id)
        ->assertSee('Nieuwsbrief')
        ->call('openFolder', $sub->id)
        ->assertSet('currentFolderId', $sub->id)
        ->call('openFolder', null)
        ->assertSet('currentFolderId', null)
        ->assertSee('Systeemberichten')
        ->assertSee('Mailings');
});

it('maakt een submap aan onder Mailings', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $this->mailings->id)
        ->set('newFolderName', 'Actie-mails')
        ->call('addFolder')
        ->assertHasNoErrors();

    expect(MessageTemplateFolder::query()->where('name', 'Actie-mails')->where('parent_id', $this->mailings->id)->exists())->toBeTrue();
});

it('weigert een lege naam bij het aanmaken van een map', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $this->mailings->id)
        ->set('newFolderName', '   ')
        ->call('addFolder')
        ->assertHasErrors('newFolderName');
});

it('kan geen map aanmaken op het virtuele root-niveau', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->set('newFolderName', 'Iets')
        ->call('addFolder');

    expect(MessageTemplateFolder::query()->where('name', 'Iets')->exists())->toBeFalse();
});

it('weigert de root-mappen te hernoemen of te verwijderen', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)->call('startRenameFolder', $this->mailings->id);
    expect($this->mailings->fresh()->name)->toBe('Mailings');

    Livewire::test(MessageTemplateBeheer::class)->call('deleteFolder', $this->systeemberichten->id);
    expect(MessageTemplateFolder::query()->find($this->systeemberichten->id))->not->toBeNull();
});

it('hernoemt een gewone map', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Oud', 'parent_id' => $this->mailings->id]);
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('startRenameFolder', $sub->id)
        ->set('editingFolderName', 'Nieuw')
        ->call('renameFolder')
        ->assertHasNoErrors();

    expect($sub->fresh()->name)->toBe('Nieuw');
});

it('weigert het verwijderen van een niet-lege map', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    MessageTemplate::create([
        'key' => 'x', 'name' => 'X', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'redactioneel', 'message_template_folder_id' => $sub->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)->call('deleteFolder', $sub->id);

    expect(MessageTemplateFolder::query()->find($sub->id))->not->toBeNull();
});

it('verwijdert een lege map', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Leeg', 'parent_id' => $this->mailings->id]);
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)->call('deleteFolder', $sub->id);

    expect(MessageTemplateFolder::query()->find($sub->id))->toBeNull();
});

it('maakt een nieuw sjabloon aan in een map onder Mailings, met automatisch gegenereerde sleutel', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $sub->id)
        ->set('name', 'Welkomstmail')
        ->set('subject', 'Welkom!')
        ->call('addBlock', 'tekst')
        ->set('blocks.0.content.html', '<p>Hallo {{voornaam}}</p>')
        ->call('save')
        ->assertHasNoErrors();

    $template = MessageTemplate::query()->where('name', 'Welkomstmail')->firstOrFail();
    expect($template->key)->toBe('welkomstmail')
        ->and($template->type->value)->toBe('redactioneel')
        ->and($template->message_template_folder_id)->toBe($sub->id);
});

it('disambigueert de gegenereerde sleutel bij een naamsbotsing', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    MessageTemplate::create([
        'key' => 'welkomstmail', 'name' => 'Bestaand', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'redactioneel', 'message_template_folder_id' => $sub->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $sub->id)
        ->set('name', 'Welkomstmail')
        ->set('subject', 'Welkom!')
        ->call('addBlock', 'tekst')
        ->set('blocks.0.content.html', '<p>Hallo</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect(MessageTemplate::query()->where('key', 'welkomstmail_2')->exists())->toBeTrue();
});

it('weigert een sjabloon aan te maken in Systeemberichten, ook via directe state-manipulatie', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->set('currentFolderId', $this->systeemberichten->id)
        ->set('name', 'Stiekem')
        ->set('subject', 'X')
        ->call('addBlock', 'tekst')
        ->set('blocks.0.content.html', '<p>X</p>')
        ->call('save')
        ->assertHasErrors('name');

    expect(MessageTemplate::query()->where('name', 'Stiekem')->exists())->toBeFalse();
});

it('toont geen sjabloon-sleutel meer in de gerenderde pagina', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    MessageTemplate::create([
        'key' => 'geheime_sleutel', 'name' => 'Zichtbare titel', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'redactioneel', 'message_template_folder_id' => $sub->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $sub->id)
        ->assertSee('Zichtbare titel')
        ->assertDontSee('geheime_sleutel');
});

it('bewerkt onderwerp en blokken van een bestaand sjabloon zonder de map of sleutel te wijzigen', function () {
    $template = MessageTemplate::create([
        'key' => 'password_reset', 'name' => 'Wachtwoord', 'subject' => 'Oud onderwerp',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>Oud</p>']]],
        'type' => 'transactioneel', 'message_template_folder_id' => $this->systeemberichten->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('edit', $template->id)
        ->set('subject', 'Nieuw onderwerp')
        ->set('blocks.0.content.html', '<p>Nieuw</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->key)->toBe('password_reset')
        ->and($template->fresh()->subject)->toBe('Nieuw onderwerp')
        ->and($template->fresh()->message_template_folder_id)->toBe($this->systeemberichten->id);
});

it('kan een systeemsjabloon niet verwijderen', function () {
    $template = MessageTemplate::create([
        'key' => 'password_reset', 'name' => 'Wachtwoord', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'transactioneel', 'message_template_folder_id' => $this->systeemberichten->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)->call('deleteTemplate', $template->id);

    expect(MessageTemplate::query()->find($template->id))->not->toBeNull();
});

it('verwijdert een mailing-sjabloon', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    $template = MessageTemplate::create([
        'key' => 'nieuwsbrief_x', 'name' => 'Editie X', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'redactioneel', 'message_template_folder_id' => $sub->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)->call('deleteTemplate', $template->id);

    expect(MessageTemplate::query()->find($template->id))->toBeNull();
});

it('weigert opslaan zonder minstens één blok', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $sub->id)
        ->set('name', 'Leeg')
        ->set('subject', 'X')
        ->call('save')
        ->assertHasErrors('blocks');

    expect(MessageTemplate::query()->where('name', 'Leeg')->exists())->toBeFalse();
});

it('weigert opslaan met een onvolledig knop-blok', function () {
    $sub = MessageTemplateFolder::create(['name' => 'Nieuwsbrief', 'parent_id' => $this->mailings->id]);
    $this->actingAs($this->beheerder);

    Livewire::test(MessageTemplateBeheer::class)
        ->call('openFolder', $sub->id)
        ->set('name', 'Onvolledig')
        ->set('subject', 'X')
        ->call('addBlock', 'knop')
        ->set('blocks.0.content.label', 'Klik hier')
        // href blijft leeg
        ->call('save')
        ->assertHasErrors('blocks.0');

    expect(MessageTemplate::query()->where('name', 'Onvolledig')->exists())->toBeFalse();
});

it('verwijdert en herordent blokken', function () {
    $this->actingAs($this->beheerder);

    $component = Livewire::test(MessageTemplateBeheer::class)
        ->call('addBlock', 'tekst')
        ->set('blocks.0.content.html', '<p>Een</p>')
        ->call('addBlock', 'scheiding')
        ->call('addBlock', 'citaat')
        ->set('blocks.2.content.text', 'Twee');

    expect($component->get('blocks'))->toHaveCount(3);

    $component->call('moveBlock', 2, 'up');
    expect($component->get('blocks.1.type'))->toBe('citaat')
        ->and($component->get('blocks.2.type'))->toBe('scheiding');

    $component->call('removeBlock', 1);
    expect($component->get('blocks'))->toHaveCount(2)
        ->and($component->get('blocks.0.type'))->toBe('tekst')
        ->and($component->get('blocks.1.type'))->toBe('scheiding');
});

it('bepaalt de beschikbare variabelen systeemzijdig uit de registry op basis van de sleutel van het sjabloon', function () {
    $template = MessageTemplate::create([
        'key' => 'enrollment_confirmed', 'name' => 'Inschrijfbevestiging (bevestigd)', 'subject' => 'X',
        'body' => [['type' => 'tekst', 'content' => ['html' => '<p>X</p>']]],
        'type' => 'transactioneel', 'message_template_folder_id' => $this->systeemberichten->id,
    ]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(MessageTemplateBeheer::class)->call('edit', $template->id);

    expect($component->get('availableVariables'))->toBe(['voornaam', 'achternaam', 'titel', 'datum', 'locatie_regel', 'activiteit_url']);
});
