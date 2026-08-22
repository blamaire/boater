<?php

use App\Services\Contact\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('slaat verificatie over als er geen secret-key geconfigureerd is', function () {
    config(['services.turnstile.secret_key' => null]);
    Http::fake();

    expect(app(TurnstileVerifier::class)->verify('', '127.0.0.1'))->toBeTrue();
    Http::assertNothingSent();
});

it('accepteert een geldig token', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    expect(app(TurnstileVerifier::class)->verify('geldig-token', '127.0.0.1'))->toBeTrue();
});

it('weigert een mislukte verificatie', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false]),
    ]);

    expect(app(TurnstileVerifier::class)->verify('ongeldig-token', '127.0.0.1'))->toBeFalse();
});

it('faalt open bij een netwerkfout richting Cloudflare', function () {
    config(['services.turnstile.secret_key' => 'test-secret']);
    Http::fake(function () {
        throw new ConnectionException('timeout');
    });

    expect(app(TurnstileVerifier::class)->verify('token', '127.0.0.1'))->toBeTrue();
});
