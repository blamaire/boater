<?php

namespace App\Livewire\Public;

use App\Enums\ContactPreferredMethod;
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

    public string $preferred_contact_method = 'mailen';

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
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:200', 'required_without:phone'],
            'preferred_contact_method' => ['required', 'in:bellen,mailen'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'phone.required_without' => 'Vul een telefoonnummer of e-mailadres in.',
            'email.required_without' => 'Vul een telefoonnummer of e-mailadres in.',
        ]);

        $preferredMethod = ContactPreferredMethod::from($data['preferred_contact_method']);

        if ($preferredMethod === ContactPreferredMethod::Bellen && blank($data['phone'])) {
            $this->addError('phone', 'Vul een telefoonnummer in als je liever gebeld wordt.');

            return;
        }
        if ($preferredMethod === ContactPreferredMethod::Mailen && blank($data['email'])) {
            $this->addError('email', 'Vul een e-mailadres in als je liever gemaild wordt.');

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
            preferredMethod: $preferredMethod,
            message: $data['message'],
            ipAddress: request()->ip(),
        );

        // Pas na een succesvolle, verwerkte indiening tellen — typefouten van
        // een echte bezoeker (validatiefout, afgewezen Turnstile) mogen het
        // venster niet onnodig opvullen, dus hit() gebeurt bewust pas hier.
        RateLimiter::hit($key, 600);

        $this->reset(['name', 'phone', 'email', 'message', 'turnstileToken']);
        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.public.contact', [
            'topics' => ContactTopic::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
