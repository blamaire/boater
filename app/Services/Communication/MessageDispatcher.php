<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\MessageType;
use App\Mail\TemplatedMail;
use App\Models\CommunicationLog;
use App\Models\MessageTemplate;
use App\Models\Person;
use App\Services\Cms\BlockContentSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * De enige plek die daadwerkelijk een sjabloon-gebaseerde e-mail verstuurt
 * (§24) — laadt het `MessageTemplate`, substitueert `{{variabele}}`-tokens,
 * saneert de body, verstuurt via `TemplatedMail` (queued) en registreert het
 * contactmoment in `CommunicationLog` (§30.1, automatische registratie).
 */
class MessageDispatcher
{
    public function __construct(private readonly BlockContentSanitizer $sanitizer) {}

    /**
     * @param  array<string, string>  $variables  bv. ['{{voornaam}}' => 'Jan'] — inclusief eventuele
     *                                            kant-en-klare HTML-snippets (zie {@see self::actionLink()}),
     *                                            die dus vóór sanering al veilig moeten zijn.
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
        $body = $this->sanitizer->sanitizeHtml(strtr($template->body, $variables));

        Mail::to($toEmail)->queue(new TemplatedMail($subject, $body));

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

    /**
     * Bouwt een veilige, al-toegestane HTML-snippet voor een call-to-action-
     * link in een sjabloon-body — geen `style`-attribuut (de sanitizer staat
     * dat niet toe op `<a>`) en geen los kleurdesign, e-mailclients
     * ondersteunen dat toch onbetrouwbaar; vetgedrukte tekst volstaat.
     */
    public static function actionLink(string $label, string $url): string
    {
        return '<p><strong><a href="'.e($url).'">'.e($label).'</a></strong></p>';
    }

    /**
     * Enkele redactionele categorie in v1 ('nieuwsbrief') — zodra er meer
     * categorieën komen, moet deze check een categorie-parameter krijgen.
     */
    private function isOptedIn(Person $recipient): bool
    {
        return false;
    }
}
