<?php

namespace App\Services\Finance;

use App\Enums\MembershipStatus;
use App\Models\Charge;
use App\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Contributie-run (§23.4, B2): per actief lidmaatschap de contributiepost voor
 * een verenigingsjaar bepalen en (op aanvraag) aanmaken.
 *
 * Verenigingsjaar = kalenderjaar. Instroom in de eerste helft (jan-jun) van
 * het doeljaar betaalt het volle jaartarief, instroom in de tweede helft
 * (jul-dec) de helft. Een doorlopend lidmaatschap (gestart in een eerder
 * jaar) betaalt altijd het volle tarief — de halvering geldt alleen voor het
 * instroomjaar zelf. Bedrag onbekend (geen geldende ProductPrice) of dit jaar
 * al een post (Charge) aangemaakt: regel wordt getoond maar bij run()
 * overgeslagen. Dit maakt alleen de post aan — bundelen tot een factuur
 * (BillingService::invoiceOpenCharges) is een aparte, latere stap.
 */
class ContributionRunService
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    /** @return Collection<int, ContributionRunLine> */
    public function preview(int $year): Collection
    {
        $yearStart = Carbon::create($year, 1, 1);

        return Membership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('type', fn ($q) => $q->whereNotNull('product_id'))
            ->with(['type.product.prices', 'billingPerson', 'person'])
            ->get()
            ->map(function (Membership $membership) use ($year, $yearStart): ContributionRunLine {
                $isHalfRate = $membership->start_date !== null
                    && $membership->start_date->year === $year
                    && $membership->start_date->month >= 7;

                $price = $membership->type->product->priceOn($yearStart);
                $amount = $price === null
                    ? null
                    : number_format($isHalfRate ? ((float) $price->amount) / 2 : (float) $price->amount, 2, '.', '');

                $alreadyCharged = Charge::query()
                    ->where('subject_type', Membership::class)
                    ->where('subject_id', $membership->id)
                    ->where('period', (string) $year)
                    ->exists();

                return new ContributionRunLine($membership, $amount, $isHalfRate, $alreadyCharged);
            });
    }

    /** @return array{created: int, skipped: int, total: string} */
    public function run(int $year): array
    {
        $created = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($this->preview($year) as $line) {
            if ($line->alreadyCharged || $line->amount === null) {
                $skipped++;

                continue;
            }

            $membership = $line->membership;
            $debtor = $membership->billingPerson ?? $membership->person;

            $this->billing->createCharge(
                product: $membership->type->product,
                debtor: $debtor,
                amount: $line->amount,
                description: "Contributie {$year} — {$membership->type->name}",
                subject: $membership,
                period: (string) $year,
            );

            $created++;
            $total += (float) $line->amount;
        }

        return ['created' => $created, 'skipped' => $skipped, 'total' => number_format($total, 2, '.', '')];
    }
}
