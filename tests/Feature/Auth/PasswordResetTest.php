<?php

use App\Mail\TemplatedMail;
use App\Models\User;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);
});

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->mailSubject === 'Wachtwoord opnieuw instellen');
});

function extractResetTokenFromMail(TemplatedMail $mail): string
{
    preg_match('#/reset-password/([^"&?]+)#', $mail->bodyHtml, $matches);

    return $matches[1] ?? '';
}

test('reset password screen can be rendered', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) {
        $response = $this->get('/reset-password/'.extractResetTokenFromMail($mail));

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => extractResetTokenFromMail($mail),
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
