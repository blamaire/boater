<?php

use App\Mail\TemplatedMail;
use App\Models\CommunicationLog;
use App\Models\CommunicationPreference;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateFolder;
use App\Models\Person;
use App\Services\Communication\MessageDispatcher;
use Database\Seeders\MessageTemplateFolderSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(MessageTemplateFolderSeeder::class);
});

function createTemplate(string $type = 'transactioneel'): MessageTemplate
{
    $folder = MessageTemplateFolder::query()->where('name', $type === 'redactioneel' ? 'Mailings' : 'Systeemberichten')->firstOrFail();

    return MessageTemplate::create([
        'key' => 'test_template',
        'name' => 'Test',
        'subject' => 'Onderwerp voor {{voornaam}}',
        'body' => [
            ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{voornaam}}</p><script>alert(1)</script>']],
            ['type' => 'knop', 'content' => ['label' => 'Klik hier', 'href' => '{{actie_url}}']],
        ],
        'type' => $type,
        'message_template_folder_id' => $folder->id,
    ]);
}

it('substitueert variabelen per blok-veld, saneert de tekst-blokken en verstuurt de mail', function () {
    Mail::fake();
    createTemplate();
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send(
        'test_template',
        'jan@example.test',
        ['{{voornaam}}' => 'Jan', '{{actie_url}}' => 'https://example.test/actie'],
        recipient: $person,
    );

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) {
        return $mail->mailSubject === 'Onderwerp voor Jan'
            && str_contains($mail->bodyHtml, 'Hallo Jan')
            && str_contains($mail->bodyHtml, 'Klik hier')
            && str_contains($mail->bodyHtml, 'https://example.test/actie')
            && ! str_contains($mail->bodyHtml, '<script>');
    });
});

it('registreert een contactmoment in het communicatielogboek', function () {
    Mail::fake();
    createTemplate();
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_url}}' => '#'], recipient: $person);

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

    app(MessageDispatcher::class)->send('test_template', 'los@example.test', ['{{voornaam}}' => 'Los', '{{actie_url}}' => '#']);

    $log = CommunicationLog::query()->firstOrFail();
    expect($log->person_id)->toBeNull()
        ->and($log->email)->toBe('los@example.test');
});

it('slaat redactionele mail over voor een ontvanger zonder opt-in', function () {
    Mail::fake();
    createTemplate('redactioneel');
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_url}}' => '#'], recipient: $person);

    Mail::assertNothingQueued();
    expect(CommunicationLog::query()->count())->toBe(0);
});

it('verstuurt redactionele mail met een ondertekende afmeldlink voor een opted-in ontvanger', function () {
    Mail::fake();
    createTemplate('redactioneel');
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);
    CommunicationPreference::create(['person_id' => $person->id, 'category' => 'nieuwsbrief', 'opted_in' => true]);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_url}}' => '#'], recipient: $person);

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) use ($person) {
        return $mail->unsubscribeUrl !== null
            && str_contains($mail->unsubscribeUrl, "/communicatievoorkeuren/afmelden/{$person->id}/nieuwsbrief")
            && str_contains($mail->unsubscribeUrl, 'signature=');
    });
});

it('voegt geen afmeldlink toe aan transactionele mail', function () {
    Mail::fake();
    createTemplate('transactioneel');
    $person = Person::create(['first_name' => 'Jan', 'last_name' => 'Test', 'email' => 'jan@example.test']);

    app(MessageDispatcher::class)->send('test_template', 'jan@example.test', ['{{voornaam}}' => 'Jan', '{{actie_url}}' => '#'], recipient: $person);

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->unsubscribeUrl === null);
});
