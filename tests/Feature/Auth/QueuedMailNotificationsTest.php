<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;

test('registratie triggert een queueable e-mailverificatie-notificatie', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Test Gebruiker',
        'email' => 'test-queued@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::query()->where('email', 'test-queued@example.com')->firstOrFail();

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('wachtwoord-reset-verzoek triggert een queueable reset-notificatie', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('e-mailverificatie-notificatie roept sendEmailVerificationNotification aan met queueable class', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('wachtwoord-reset-notificatie op user is queueable', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->sendPasswordResetNotification('test-token');

    Notification::assertSentTo(
        $user,
        QueuedResetPassword::class,
        function (QueuedResetPassword $notification) {
            expect($notification->token)->toBe('test-token');

            return true;
        },
    );
});
