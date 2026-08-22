<?php

use App\Livewire\Public\Contact;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\Person;
use App\Notifications\ContactRequestSubmitted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function contactFlowTopic(): ContactTopic
{
    $person = Person::create(['first_name' => 'Ver', 'last_name' => 'Antwoordelijke', 'email' => 'verantwoordelijke@example.test']);

    return ContactTopic::create(['name' => 'Algemeen', 'responsible_person_id' => $person->id]);
}

beforeEach(function () {
    RateLimiter::clear('contact-formulier:127.0.0.1');
});

it('toont het contactformulier met de geseede onderwerpen op /contact', function () {
    $topic = contactFlowTopic();

    $this->get('/contact')
        ->assertOk()
        ->assertSee('Contact')
        ->assertSee($topic->name);
});

it('toont het telefoon-/e-mailveld pas nadat de bijbehorende contactwijze is aangevinkt', function () {
    $component = Livewire::test(Contact::class)
        ->assertDontSee('wire:model="phone"', false)
        ->assertDontSee('wire:model="email"', false);

    $component->set('contact_by_phone', true)
        ->assertSee('wire:model="phone"', false)
        ->assertDontSee('wire:model="email"', false);

    $component->set('contact_by_email', true)
        ->assertSee('wire:model="phone"', false)
        ->assertSee('wire:model="email"', false);
});

it('dient een geldig verzoek in met alleen e-mail en mailt de verantwoordelijke', function () {
    Notification::fake();
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Ik heb een vraag over het lidmaatschap.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $request = ContactRequest::query()->firstOrFail();
    expect($request->name)->toBe('Jan Jansen')
        ->and($request->email)->toBe('jan@example.test')
        ->and($request->contact_by_email)->toBeTrue()
        ->and($request->contact_by_phone)->toBeFalse()
        ->and($request->status->value)->toBe('nieuw');

    Notification::assertSentOnDemand(ContactRequestSubmitted::class, function ($notification, $channels, $notifiable) {
        return ($notifiable->routes['mail'] ?? null) === 'verantwoordelijke@example.test';
    });
});

it('slaat beide contactvoorkeuren op als zowel bellen als mailen zijn aangevinkt', function () {
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_phone', true)
        ->set('phone', '0612345678')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Bericht van voldoende lengte.')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ContactRequest::query()->firstOrFail();
    expect($request->contact_by_phone)->toBeTrue()
        ->and($request->contact_by_email)->toBeTrue();
});

it('weigert indiening als geen enkele contactwijze is aangevinkt', function () {
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('message', 'Bericht van voldoende lengte.')
        ->call('submit')
        ->assertHasErrors('form');

    expect(ContactRequest::count())->toBe(0);
});

it('weigert "bel me terug" zonder telefoonnummer', function () {
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_phone', true)
        ->set('message', 'Bericht van voldoende lengte.')
        ->call('submit')
        ->assertHasErrors('phone');

    expect(ContactRequest::count())->toBe(0);
});

it('negeert een indiening stil als het honeypot-veld is ingevuld', function () {
    Notification::fake();
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Spambot')
        ->set('contact_by_email', true)
        ->set('email', 'spam@example.test')
        ->set('message', 'Geautomatiseerd spambericht.')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(ContactRequest::count())->toBe(0);
    Notification::assertNothingSent();
});

it('blokkeert na te veel verzoeken vanaf hetzelfde IP', function () {
    $topic = contactFlowTopic();

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Contact::class)
            ->set('contact_topic_id', $topic->id)
            ->set('name', 'Jan Jansen')
            ->set('contact_by_email', true)
            ->set('email', 'jan@example.test')
            ->set('message', 'Bericht van voldoende lengte.')
            ->call('submit')
            ->assertHasNoErrors();
    }

    expect(ContactRequest::count())->toBe(5);

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Dit zesde verzoek mag niet meer door.')
        ->call('submit')
        ->assertHasErrors('form');

    expect(ContactRequest::count())->toBe(5);
});

it('slaat Turnstile-verificatie over als er geen secret-key geconfigureerd is', function () {
    config(['services.turnstile.secret_key' => null]);
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Bericht van voldoende lengte.')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ContactRequest::count())->toBe(1);
});

it('weigert indiening als Turnstile de verificatie afwijst', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Bericht van voldoende lengte.')
        ->set('turnstileToken', 'token')
        ->call('submit')
        ->assertHasErrors('form');

    expect(ContactRequest::count())->toBe(0);
});

it('accepteert indiening (fail-open) als Turnstile onbereikbaar is', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });
    $topic = contactFlowTopic();

    Livewire::test(Contact::class)
        ->set('contact_topic_id', $topic->id)
        ->set('name', 'Jan Jansen')
        ->set('contact_by_email', true)
        ->set('email', 'jan@example.test')
        ->set('message', 'Bericht van voldoende lengte.')
        ->set('turnstileToken', 'token')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ContactRequest::count())->toBe(1);
});
