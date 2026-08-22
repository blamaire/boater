<?php

namespace App\Livewire\Public;

use App\Models\ContactTopic;
use App\Services\Contact\ContactRequestService;
use App\Services\Contact\TurnstileVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publiek contactformulier ("bel/mail me terug", §1). Drie anti-spam-lagen:
 * een honeypot-veld (bots vullen 'm in, mensen zien 'm niet), server-side
 * rate-limiting per IP, en Cloudflare Turnstile als extra laag.
 *
 * Livewire-acties lopen via `/livewire/update`, niet via de named route —
 * een route-throttle-middleware zou dus alleen de eerste pagina-load
 * beperken. De rate-limiter zit daarom in `submit()` zelf.
 */
#[Layout('components.public-layout', ['title' => 'Contact'])]
class Contact extends Component
{
    public ?int $contact_topic_id = null;

    public string $name = '';

    public ?string $phone = null;

    public ?string $email = null;

    /** Beide mogen aangevinkt zijn — bepaalt of het telefoon-/e-mailveld getoond wordt. */
    public bool $contact_by_phone = false;

    public bool $contact_by_email = false;

    public string $message = '';

    /** Honeypot: onzichtbaar voor mensen, bots vullen dit vaak automatisch in. */
    public string $website = '';

    /** Door de Turnstile-widget's JS-callback gezet. */
    public string $turnstileToken = '';

    public bool $submitted = false;

    public function submit(ContactRequestService $service, TurnstileVerifier $turnstile): void
    {
        if ($this->website !== '') {
            // Stil negeren: geen foutmelding die een spammer tips geeft.
            $this->submitted = true;

            return;
        }

        $key = 'contact-formulier:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('form', 'Te veel verzoeken vanaf dit adres. Probeer het over '.ceil($seconds / 60).' minuten opnieuw.');

            return;
        }

        $data = $this->validate([
            'contact_topic_id' => ['required', 'integer', 'exists:contact_topics,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if (! $this->contact_by_phone && ! $this->contact_by_email) {
            $this->addError('form', 'Kies of je gebeld en/of gemaild wilt worden.');

            return;
        }

        if ($this->contact_by_phone && blank($data['phone'])) {
            $this->addError('phone', 'Vul een telefoonnummer in als je gebeld wilt worden.');

            return;
        }

        if ($this->contact_by_email && blank($data['email'])) {
            $this->addError('email', 'Vul een e-mailadres in als je gemaild wilt worden.');

            return;
        }

        if (! $turnstile->verify($this->turnstileToken, request()->ip())) {
            $this->addError('form', 'De verificatie is mislukt. Probeer het opnieuw.');

            return;
        }

        $topic = ContactTopic::query()->findOrFail($data['contact_topic_id']);

        $service->submit(
            topic: $topic,
            name: $data['name'],
            phone: $data['phone'],
            email: $data['email'],
            contactByPhone: $this->contact_by_phone,
            contactByEmail: $this->contact_by_email,
            message: $data['message'],
            ipAddress: request()->ip(),
        );

        // Pas na een succesvolle, verwerkte indiening tellen — typefouten van
        // een echte bezoeker (validatiefout, afgewezen Turnstile) mogen het
        // venster niet onnodig opvullen, dus hit() gebeurt bewust pas hier.
        RateLimiter::hit($key, 600);

        $this->reset(['name', 'phone', 'email', 'contact_by_phone', 'contact_by_email', 'message', 'turnstileToken']);
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.public.contact', [
            'topics' => ContactTopic::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
