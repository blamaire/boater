<?php

use App\Enums\ContactRequestStatus;
use App\Livewire\Admin\ContactVerzoekDetail;
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

function contactDetailManagerUser(): User
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

function contactDetailEntry(): ContactRequest
{
    $responsible = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke', 'email' => 'v@example.test']);
    $topic = ContactTopic::create(['name' => 'Algemeen', 'responsible_person_id' => $responsible->id]);

    return ContactRequest::create([
        'contact_topic_id' => $topic->id,
        'name' => 'Piet Puk',
        'phone' => '0612345678',
        'email' => 'piet@example.test',
        'preferred_contact_method' => 'bellen',
        'message' => 'Ik heb een vraag over materiaal.',
        'status' => 'nieuw',
    ]);
}

it('vereist contact_requests.manage om een contactverzoek te bekijken', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Jan', 'last_name' => 'Lid', 'account_id' => $user->id]);
    $request = contactDetailEntry();

    $this->actingAs($user)->get('/beheer/contactverzoeken/'.$request->id)->assertForbidden();
});

it('toont alle velden van een contactverzoek', function () {
    $request = contactDetailEntry();

    $this->actingAs(contactDetailManagerUser())
        ->get('/beheer/contactverzoeken/'.$request->id)
        ->assertOk()
        ->assertSee('Piet Puk')
        ->assertSee('0612345678')
        ->assertSee('piet@example.test')
        ->assertSee('Ik heb een vraag over materiaal.');
});

it('werkt de status bij vanaf het detailscherm en legt het vast in het auditlogboek — bevestigt de link uit de notificatiemail werkt', function () {
    $request = contactDetailEntry();

    Livewire::actingAs(contactDetailManagerUser())
        ->test(ContactVerzoekDetail::class, ['contactRequest' => $request])
        ->call('updateStatus', ContactRequestStatus::Afgehandeld->value);

    expect($request->fresh()->status)->toBe(ContactRequestStatus::Afgehandeld);

    $entry = AuditEntry::where('action', 'contact_request.status_changed')->where('subject_id', $request->id)->firstOrFail();
    expect($entry->after['status'])->toBe('afgehandeld');
});
