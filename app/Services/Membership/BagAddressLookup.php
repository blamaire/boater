<?php

namespace App\Services\Membership;

use App\Support\Address;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BAG-adreslookup via PDOK LocatieServer (§10.x — geen API-key nodig).
 *
 * Neemt een Nederlandse postcode + huisnummer en levert (indien uniek gevonden)
 * straat, plaats en officiële postcode. Bij twijfel of geen match: null.
 */
class BagAddressLookup
{
    private const ENDPOINT = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

    public function lookup(string $postalCode, string $houseNumber, ?string $addition = null): ?Address
    {
        $postalCode = strtoupper(preg_replace('/\s+/', '', $postalCode) ?? '');
        $houseNumber = trim($houseNumber);
        $addition = $addition !== null ? trim($addition) : null;

        if ($postalCode === '' || $houseNumber === '' || preg_match('/^\d{4}[A-Z]{2}$/', $postalCode) !== 1) {
            return null;
        }

        $query = 'postcode:'.$postalCode.' and huisnummer:'.$houseNumber;
        if ($addition !== null && $addition !== '') {
            $query .= ' and huisnummertoevoeging:'.$addition;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'fq' => 'type:adres',
                    'rows' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::warning('BAG-lookup mislukt', ['exception' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $doc = $response->json('response.docs.0');
        if (! is_array($doc)) {
            return null;
        }

        $straatnaam = $doc['straatnaam'] ?? null;
        $huisnummer = $doc['huisnummer'] ?? null;
        $postcode = $doc['postcode'] ?? null;
        $woonplaats = $doc['woonplaatsnaam'] ?? null;

        if (! is_string($straatnaam) || ! is_string($postcode) || ! is_string($woonplaats) || $huisnummer === null) {
            return null;
        }

        $toevoeging = $doc['huisnummertoevoeging'] ?? null;
        if (is_string($toevoeging) && trim($toevoeging) === '') {
            $toevoeging = null;
        }

        return new Address(
            street: $straatnaam,
            houseNumber: (string) $huisnummer,
            houseNumberAddition: is_string($toevoeging) ? $toevoeging : null,
            postalCode: $postcode,
            city: $woonplaats,
        );
    }
}
