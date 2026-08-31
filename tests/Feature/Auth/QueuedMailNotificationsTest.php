<?php

use App\Mail\TemplatedMail;
use App\Models\User;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);
});

test('registratie triggert een queueable e-mailverificatie-mail', function () {
    Mail::fake();

    $this->post('/register', [
        'name' => 'Test Gebruiker',
        'email' => 'test-queued@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->mailSubject === 'Bevestig je e-mailadres');
});

test('wachtwoord-reset-verzoek triggert een queueable reset-mail', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->mailSubject === 'Wachtwoord opnieuw instellen');
});

test('e-mailverificatie-mail via sendEmailVerificationNotification is queueable en bevat een geldige link', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) use ($user) {
        return $mail->mailSubject === 'Bevestig je e-mailadres'
            && str_contains($mail->bodyHtml, sha1($user->getEmailForVerification()));
    });
});

test('wachtwoord-reset-mail bevat het token in de link', function () {
    Mail::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification('test-token');

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) {
        return $mail->mailSubject === 'Wachtwoord opnieuw instellen'
            && str_contains($mail->bodyHtml, 'test-token');
    });
});
