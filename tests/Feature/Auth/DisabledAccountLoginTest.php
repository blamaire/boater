<?php

use App\Models\User;

it('weigert login voor een gedeactiveerd account met een duidelijke melding', function () {
    $user = User::factory()->create([
        'email' => 'geblokkeerd@example.com',
        'password' => bcrypt('geheim1234'),
        'email_verified_at' => now(),
        'disabled_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'geheim1234',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(session('errors')->get('email')[0])->toContain('gedeactiveerd');
});

it('laat een normaal account gewoon inloggen', function () {
    $user = User::factory()->create([
        'email' => 'ok@example.com',
        'password' => bcrypt('geheim1234'),
        'email_verified_at' => now(),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'geheim1234',
    ]);

    $this->assertAuthenticatedAs($user);
});
