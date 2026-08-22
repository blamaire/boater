<?php

use App\Enums\ContactRequestStatus;
use App\Livewire\Admin\ContactVerzoekBeheer;
use App\Models\AuditEntry;
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

function contactRequestManagerUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Beheer', 'last_name' => 'Der', 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'contact_requests.manage')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

function contactVerzoekEntry(array $overrides = []): ContactRequest
{
    $responsible = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke '.uniqid(), 'email' => 'v'.uniqid().'@example.test']);
    $topic = ContactTopic::create(['name' => $overrides['topic_name'] ?? 'Algemeen', 'responsible_person_id' => $responsible->id]);
    unset($overrides['topic_name']);

    return ContactRequest::create(array_merge([
        'contact_topic_id' => $topic->id,
        'name' => 'Bezoeker',
        'email' => 'bezoeker@example.test',
        'contact_by_email' => true,
        'message' => 'Testbericht',
        'status' => 'nieuw',
    ], $overrides));
}

it('vereist contact_requests.manage om de contactverzoeklijst te zien', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Jan', 'last_name' => 'Lid', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/beheer/contactverzoeken')->assertForbidden();
});

it('toont ingediende contactverzoeken voor een beheerder', function () {
    contactVerzoekEntry(['name' => 'Piet Puk']);

    $this->actingAs(contactRequestManagerUser())
        ->get('/beheer/contactverzoeken')
        ->assertOk()
        ->assertSee('Piet Puk');
});

it('filtert op status', function () {
    contactVerzoekEntry(['name' => 'Nieuw item', 'status' => ContactRequestStatus::Nieuw]);
    contactVerzoekEntry(['name' => 'Afgehandeld item', 'status' => ContactRequestStatus::Afgehandeld]);

    Livewire::actingAs(contactRequestManagerUser())
        ->test(ContactVerzoekBeheer::class)
        ->set('filterStatus', ContactRequestStatus::Afgehandeld->value)
        ->assertSee('Afgehandeld item')
        ->assertDontSee('Nieuw item');
});

it('filtert op onderwerp', function () {
    contactVerzoekEntry(['name' => 'Materiaalvraag', 'topic_name' => 'Materiaalbeheer']);
    contactVerzoekEntry(['name' => 'Ledenvraag', 'topic_name' => 'Lidmaatschap']);

    $materiaalTopicId = ContactTopic::where('name', 'Materiaalbeheer')->value('id');

    Livewire::actingAs(contactRequestManagerUser())
        ->test(ContactVerzoekBeheer::class)
        ->set('filterTopicId', $materiaalTopicId)
        ->assertSee('Materiaalvraag')
        ->assertDontSee('Ledenvraag');
});

it('werkt de status bij en legt het vast in het auditlogboek', function () {
    $request = contactVerzoekEntry();

    Livewire::actingAs(contactRequestManagerUser())
        ->test(ContactVerzoekBeheer::class)
        ->call('updateStatus', $request->id, ContactRequestStatus::InBehandeling->value);

    expect($request->fresh()->status)->toBe(ContactRequestStatus::InBehandeling);

    $entry = AuditEntry::where('action', 'contact_request.status_changed')->where('subject_id', $request->id)->firstOrFail();
    expect($entry->before['status'])->toBe('nieuw')
        ->and($entry->after['status'])->toBe('in_behandeling');
});
