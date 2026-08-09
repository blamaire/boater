<?php

namespace Database\Seeders;

use App\Models\BtwCode;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Standaard BTW-codes, meteen gekoppeld aan zowel de af-te-dragen- als de
 * voor-te-vorderen-rekening (hetzelfde percentage geldt voor beide richtingen).
 */
class BtwCodeSeeder extends Seeder
{
    public function run(): void
    {
        $afTeDragen = LedgerAccount::query()->where('code', '1600')->firstOrFail();
        $voorTeVorderen = LedgerAccount::query()->where('code', '1500')->firstOrFail();

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
                    'af_te_dragen_ledger_account_id' => $afTeDragen->id,
                    'voor_te_vorderen_ledger_account_id' => $voorTeVorderen->id,
                    'valid_from' => Carbon::create(2024, 1, 1),
                ],
            );
        }
    }
}
