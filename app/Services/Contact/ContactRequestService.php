<?php

namespace App\Services\Contact;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use App\Services\Communication\MessageDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Publiek contactformulier ("bel/mail me terug"). Loopt bewust niet via de
 * goedkeuringsmotor of ProposalEngine — een anonieme bezoeker heeft geen
 * Person-account om als indiener te registreren, en er is niets om goed te
 * keuren (het is een verzoek om contact, geen wijziging).
 */
class ContactRequestService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MessageDispatcher $dispatcher,
    ) {}

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
                $this->dispatcher->send('contact_request_submitted', $responsible->email, [
                    '{{onderwerp}}' => $topic->name,
                    '{{naam}}' => $name,
                    '{{voorkeur}}' => $request->contactMethodLabel(),
                    '{{telefoon_regel}}' => $phone ? 'Telefoon: '.$phone.'<br>' : '',
                    '{{email_regel}}' => $email ? 'E-mail: '.$email.'<br>' : '',
                    '{{bericht}}' => $message,
                    '{{verzoek_url}}' => url('/beheer/contactverzoeken/'.$request->id),
                ], recipient: $responsible, related: $request);
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
