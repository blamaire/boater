<?php

namespace Database\Seeders;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * Minimaal, verenigingsgangbaar rekeningschema (§23.3). Bewust klein; de
 * penningmeester kan het later uitbreiden. Codes volgen de gebruikelijke
 * grootboek-indeling (1xxx activa, 8xxx opbrengsten).
 */
class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Bank', 'type' => LedgerAccountType::Activa],
            ['code' => '1300', 'name' => 'Debiteuren', 'type' => LedgerAccountType::Activa],
            ['code' => '8000', 'name' => 'Contributiebaten', 'type' => LedgerAccountType::Opbrengsten],
            ['code' => '8100', 'name' => 'Activiteitenbaten', 'type' => LedgerAccountType::Opbrengsten],
            ['code' => '8200', 'name' => 'Sponsorbaten', 'type' => LedgerAccountType::Opbrengsten],
            ['code' => '8900', 'name' => 'Overige baten', 'type' => LedgerAccountType::Opbrengsten],
        ];

        foreach ($accounts as $account) {
            LedgerAccount::updateOrCreate(
                ['code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type']],
            );
        }
    }
}
