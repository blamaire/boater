<?php

namespace Database\Seeders;

use App\Enums\BtwCodeDirection;
use App\Models\BtwCode;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Standaard BTW-codes (verkoop). Inkoop/crediteuren bestaat nog niet, dus
 * er is nu nog geen "voor te vorderen"-code nodig.
 */
class BtwCodeSeeder extends Seeder
{
    public function run(): void
    {
        $account = LedgerAccount::query()->where('code', '1600')->firstOrFail();

        $codes = [
            ['name' => '21% hoog tarief', 'percentage' => '21.00'],
            ['name' => '9% laag tarief', 'percentage' => '9.00'],
            ['name' => '0% vrijgesteld', 'percentage' => '0.00'],
        ];

        foreach ($codes as $code) {
            BtwCode::updateOrCreate(
                ['name' => $code['name']],
                [
                    'percentage' => $code['percentage'],
                    'direction' => BtwCodeDirection::AfTeDragen,
                    'ledger_account_id' => $account->id,
                    'valid_from' => Carbon::create(2024, 1, 1),
                ],
            );
        }
    }
}
