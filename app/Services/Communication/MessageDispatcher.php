<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\MessageType;
use App\Mail\TemplatedMail;
use App\Models\CommunicationLog;
use App\Models\CommunicationPreference;
use App\Models\MessageTemplate;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * De enige plek die daadwerkelijk een sjabloon-gebaseerde e-mail verstuurt
 * (§24) — laadt het `MessageTemplate`, rendert de block-body via
 * `MessageBlockRenderer` (substitutie + sanering per block), verstuurt via
 * `TemplatedMail` (queued) en registreert het contactmoment in
 * `CommunicationLog` (§30.1, automatische registratie).
 */
class MessageDispatcher
{
    /**
     * Enkele redactionele categorie in v1 — zodra er meer categorieën komen,
     * moet `send()` een categorie-parameter krijgen i.p.v. deze hardcoded te
     * gebruiken voor zowel de opt-in-check als de afmeldlink.
     */
    private const string NEWSLETTER_CATEGORY = 'nieuwsbrief';

    public function __construct(private readonly MessageBlockRenderer $blockRenderer) {}

    /**
     * @param  array<string, string>  $variables  bv. ['{{voornaam}}' => 'Jan'] — platte tekst, geen
     *                                            HTML-snippets meer nodig (zie Knop-block voor call-to-action-links).
     */
    public function send(
        string $templateKey,
        string $toEmail,
        array $variables,
        ?Person $recipient = null,
        ?Model $related = null,
    ): void {
        $template = MessageTemplate::query()->where('key', $templateKey)->firstOrFail();

        if ($template->type === MessageType::Redactioneel && $recipient !== null && ! $this->isOptedIn($recipient)) {
            return;
        }

        $subject = strtr($template->subject, $variables);
        $body = $this->blockRenderer->render($template->body, $variables);

        $unsubscribeUrl = $template->type === MessageType::Redactioneel && $recipient !== null
            ? URL::signedRoute('communication-preferences.unsubscribe', ['person' => $recipient->id, 'category' => self::NEWSLETTER_CATEGORY])
            : null;

        Mail::to($toEmail)->queue(new TemplatedMail($subject, $body, $unsubscribeUrl));

        CommunicationLog::query()->create([
            'person_id' => $recipient?->id,
            'email' => $recipient === null ? $toEmail : null,
            'channel' => CommunicationChannel::Email,
            'direction' => CommunicationDirection::Uit,
            'subject' => $subject,
            'logged_by_person_id' => null,
            'occurred_at' => now(),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
        ]);
    }

    private function isOptedIn(Person $recipient): bool
    {
        return CommunicationPreference::query()
            ->where('person_id', $recipient->id)
            ->where('category', self::NEWSLETTER_CATEGORY)
            ->where('opted_in', true)
            ->exists();
    }
}
