<?php

use App\Mail\TemplatedMail;
use App\Models\CommunicationLog;
use App\Models\MessageTemplate;
use App\Models\Person;
use App\Services\Communication\MessageDispatcher;
use Illuminate\Support\Facades\Mail;

function createTemplate(string $type = 'transactioneel'): MessageTemplate
{
    return MessageTemplate::create([
        'key' => 'test_template',
        'name' => 'Test',
        'subject' => 'Onderwerp voor {{voornaam}}',
        'body' => '<p>Hallo {{voornaam}}, klik hier: {{actie_knop}}</p><script>alert(1)</script>',
        'type' => $type,
    ]);
}

it('substitueert variabelen, saneert de body en verstuurt de mail', function () {
    Mail::fake();
    createTemplate();
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send(
        'test_template',
        'jan@example.test',
        [
            '{{voornaam}}' => 'Jan',
            '{{actie_knop}}' => MessageDispatcher::actionLink('Klik hier', 'https://example.test/actie'),
        ],
        recipient: $person,
    );

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) {
        return $mail->mailSubject === 'Onderwerp voor Jan'
            && str_contains($mail->bodyHtml, 'Hallo Jan')
            && str_contains($mail->bodyHtml, 'Klik hier')
            && ! str_contains($mail->bodyHtml, '<script>');
    });
});

it('registreert een contactmoment in het communicatielogboek', function () {
    Mail::fake();
    createTemplate();
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_knop}}' => ''], recipient: $person);

    $log = CommunicationLog::query()->firstOrFail();
    expect($log->person_id)->toBe($person->id)
        ->and($log->email)->toBeNull()
        ->and($log->channel->value)->toBe('email')
        ->and($log->direction->value)->toBe('uit')
        ->and($log->subject)->toBe('Onderwerp voor Jan');
});

it('logt het opgegeven e-mailadres als er geen ontvanger-persoon is', function () {
    Mail::fake();
    createTemplate();

    app(MessageDispatcher::class)->send('test_template', 'los@example.test', ['{{voornaam}}' => 'Los', '{{actie_knop}}' => '']);

    $log = CommunicationLog::query()->firstOrFail();
    expect($log->person_id)->toBeNull()
        ->and($log->email)->toBe('los@example.test');
});

it('actionLink() levert geen eigen p-wrapper — sjablonen zetten {{actie_knop}} al zelf in een p', function () {
    expect(MessageDispatcher::actionLink('Klik hier', 'https://example.test/actie'))
        ->toBe('<strong><a href="https://example.test/actie">Klik hier</a></strong>');
});

it('slaat redactionele mail over voor een ontvanger zonder opt-in', function () {
    Mail::fake();
    createTemplate('redactioneel');
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_knop}}' => ''], recipient: $person);

    Mail::assertNothingQueued();
    expect(CommunicationLog::query()->count())->toBe(0);
});
