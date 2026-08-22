<?php

namespace App\Services\Contact;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\Person;
use App\Notifications\ContactRequestSubmitted;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Publiek contactformulier ("bel/mail me terug"). Loopt bewust niet via de
 * goedkeuringsmotor of ProposalEngine — een anonieme bezoeker heeft geen
 * Person-account om als indiener te registreren, en er is niets om goed te
 * keuren (het is een verzoek om contact, geen wijziging).
 */
class ContactRequestService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(
        ContactTopic $topic,
        string $name,
        ?string $phone,
        ?string $email,
        bool $contactByPhone,
        bool $contactByEmail,
        string $message,
        ?string $ipAddress,
    ): ContactRequest {
        return DB::transaction(function () use ($topic, $name, $phone, $email, $contactByPhone, $contactByEmail, $message, $ipAddress): ContactRequest {
            $request = ContactRequest::create([
                'contact_topic_id' => $topic->id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'contact_by_phone' => $contactByPhone,
                'contact_by_email' => $contactByEmail,
                'message' => $message,
                'status' => ContactRequestStatus::Nieuw,
                'ip_address' => $ipAddress,
            ]);

            // Geen AuditLogger::log() hier: anonieme indiening zonder Person-actor
            // (zelfde aanpak als de publieke "Lid worden"-flow). De audit-trail
            // begint bij de eerste statuswijziging door een beheerder.

            $responsible = $topic->responsible;
            if (filled($responsible->email)) {
                Notification::route('mail', $responsible->email)
                    ->notify(new ContactRequestSubmitted($request));
            }

            return $request;
        });
    }

    public function changeStatus(ContactRequest $request, ContactRequestStatus $to, Person $actor): void
    {
        $before = ['status' => $request->status->value];
        $request->update(['status' => $to]);

        $this->audit->log('contact_request.status_changed', $request,
            before: $before,
            after: ['status' => $to->value],
            context: ['actor_person_id' => $actor->id],
        );
    }
}
