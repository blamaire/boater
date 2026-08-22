<?php

use App\Enums\ContactRequestStatus;
use App\Models\AuditEntry;
use App\Models\ContactTopic;
use App\Models\Person;
use App\Models\User;
use App\Notifications\ContactRequestSubmitted;
use App\Services\Contact\ContactRequestService;
use Illuminate\Support\Facades\Notification;

function contactTopicWithResponsible(?string $responsibleEmail = 'verantwoordelijke@example.test'): ContactTopic
{
    $person = Person::create([
        'first_name' => 'Ver',
        'last_name' => 'Antwoordelijke',
        'email' => $responsibleEmail,
    ]);

    return ContactTopic::create(['name' => 'Algemeen', 'responsible_person_id' => $person->id]);
}

it('maakt een contactverzoek aan en mailt de verantwoordelijke van het onderwerp', function () {
    Notification::fake();
    $topic = contactTopicWithResponsible('chief@example.test');

    $request = app(ContactRequestService::class)->submit(
        topic: $topic,
        name: 'Piet Test',
        phone: null,
        email: 'piet@example.test',
        contactByPhone: false,
        contactByEmail: true,
        message: 'Testbericht',
        ipAddress: '127.0.0.1',
    );

    expect($request->contact_topic_id)->toBe($topic->id)
        ->and($request->status)->toBe(ContactRequestStatus::Nieuw);

    Notification::assertSentOnDemand(ContactRequestSubmitted::class, function ($notification, $channels, $notifiable) {
        return in_array('mail', $channels, true)
            && ($notifiable->routes['mail'] ?? null) === 'chief@example.test';
    });
});

it('maakt het verzoek wél aan als de verantwoordelijke geen e-mailadres heeft, maar stuurt geen mail', function () {
    Notification::fake();
    $topic = contactTopicWithResponsible(null);

    $request = app(ContactRequestService::class)->submit(
        topic: $topic,
        name: 'Piet Test',
        phone: '0612345678',
        email: null,
        contactByPhone: true,
        contactByEmail: false,
        message: 'Testbericht',
        ipAddress: null,
    );

    expect($request->exists)->toBeTrue();
    Notification::assertNothingSent();
});

it('wijzigt de status en legt het vast in het auditlogboek', function () {
    $topic = contactTopicWithResponsible();
    $request = app(ContactRequestService::class)->submit(
        topic: $topic,
        name: 'Piet Test',
        phone: null,
        email: 'piet@example.test',
        contactByPhone: false,
        contactByEmail: true,
        message: 'Testbericht',
        ipAddress: null,
    );

    $user = User::factory()->create(['email_verified_at' => now()]);
    $actor = Person::create(['first_name' => 'Actor', 'last_name' => 'Der', 'account_id' => $user->id]);

    app(ContactRequestService::class)->changeStatus($request, ContactRequestStatus::InBehandeling, $actor);

    expect($request->fresh()->status)->toBe(ContactRequestStatus::InBehandeling);

    $entry = AuditEntry::where('action', 'contact_request.status_changed')->where('subject_id', $request->id)->firstOrFail();
    expect($entry->before['status'])->toBe('nieuw')
        ->and($entry->after['status'])->toBe('in_behandeling');
});
