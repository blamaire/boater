<?php

use App\Livewire\Admin\ContactOnderwerpBeheer;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function contactTopicManagerUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Beheer', 'last_name' => 'Der', 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'contact_topics.manage')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

it('vereist contact_topics.manage om onderwerpen te beheren', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Jan', 'last_name' => 'Lid', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/beheer/contact-onderwerpen')->assertForbidden();
});

it('maakt een onderwerp inline aan', function () {
    $responsible = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke', 'email' => 'v@example.test']);

    Livewire::actingAs(contactTopicManagerUser())
        ->test(ContactOnderwerpBeheer::class)
        ->set('name', 'Materiaalbeheer')
        ->set('responsible_person_id', $responsible->id)
        ->call('save')
        ->assertHasNoErrors();

    $topic = ContactTopic::query()->firstOrFail();
    expect($topic->name)->toBe('Materiaalbeheer')
        ->and($topic->responsible_person_id)->toBe($responsible->id);
});

it('bewerkt een bestaand onderwerp inline', function () {
    $responsible = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke', 'email' => 'v@example.test']);
    $other = Person::create(['first_name' => 'An', 'last_name' => 'Der', 'email' => 'a@example.test']);
    $topic = ContactTopic::create(['name' => 'Algemeen', 'responsible_person_id' => $responsible->id]);

    Livewire::actingAs(contactTopicManagerUser())
        ->test(ContactOnderwerpBeheer::class)
        ->call('edit', $topic->id)
        ->set('name', 'Bestuur')
        ->set('responsible_person_id', $other->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($topic->fresh()->name)->toBe('Bestuur')
        ->and($topic->fresh()->responsible_person_id)->toBe($other->id);
});

it('blokkeert verwijderen van een onderwerp met bestaande contactverzoeken', function () {
    $responsible = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke', 'email' => 'v@example.test']);
    $topic = ContactTopic::create(['name' => 'Algemeen', 'responsible_person_id' => $responsible->id]);
    ContactRequest::create([
        'contact_topic_id' => $topic->id,
        'name' => 'Bezoeker',
        'email' => 'bezoeker@example.test',
        'preferred_contact_method' => 'mailen',
        'message' => 'Vraag',
        'status' => 'nieuw',
    ]);

    Livewire::actingAs(contactTopicManagerUser())
        ->test(ContactOnderwerpBeheer::class)
        ->call('delete', $topic->id);

    expect(ContactTopic::query()->find($topic->id))->not->toBeNull();
});
