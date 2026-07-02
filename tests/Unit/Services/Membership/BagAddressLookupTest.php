<?php

use App\Services\Membership\BagAddressLookup;
use Illuminate\Support\Facades\Http;

it('resolves postcode + huisnummer via PDOK LocatieServer', function () {
    Http::fake([
        'api.pdok.nl/*' => Http::response([
            'response' => [
                'numFound' => 1,
                'docs' => [[
                    'type' => 'adres',
                    'straatnaam' => 'Hoofdstraat',
                    'huisnummer' => 12,
                    'huisnummertoevoeging' => '',
                    'postcode' => '1234AB',
                    'woonplaatsnaam' => 'Gouda',
                ]],
            ],
        ]),
    ]);

    $address = app(BagAddressLookup::class)->lookup('1234 ab', '12');

    expect($address)->not->toBeNull()
        ->and($address->street)->toBe('Hoofdstraat')
        ->and($address->houseNumber)->toBe('12')
        ->and($address->postalCode)->toBe('1234AB')
        ->and($address->city)->toBe('Gouda')
        ->and($address->houseNumberAddition)->toBeNull();
});

it('returns null when PDOK returns no results', function () {
    Http::fake([
        'api.pdok.nl/*' => Http::response(['response' => ['numFound' => 0, 'docs' => []]]),
    ]);

    expect(app(BagAddressLookup::class)->lookup('1234AB', '999'))->toBeNull();
});

it('validates postcode format before calling the API', function () {
    Http::fake();

    expect(app(BagAddressLookup::class)->lookup('foutcode', '1'))->toBeNull();

    Http::assertNothingSent();
});
