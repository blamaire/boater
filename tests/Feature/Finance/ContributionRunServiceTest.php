<?php

use App\Enums\MembershipStatus;
use App\Models\Charge;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Finance\ContributionRunService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;

beforeEach(function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);
    $this->run = app(ContributionRunService::class);

    $this->product = Product::create(['name' => 'Contributie A-lid', 'type' => 'contributie']);
    ProductPrice::create(['product_id' => $this->product->id, 'valid_from' => '2026-01-01', 'amount' => '120.00']);
    $this->type = MembershipType::create(['key' => 'a-run-test', 'name' => 'A-lid', 'product_id' => $this->product->id]);
});

function maakLid(string $naam, string $startDate, MembershipStatus $status = MembershipStatus::Active): Membership
{
    $type = MembershipType::where('key', 'a-run-test')->firstOrFail();
    $person = Person::create(['first_name' => $naam, 'last_name' => 'Test']);

    return Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => $status,
        'start_date' => $startDate,
        'billing_person_id' => $person->id,
    ]);
}

it('rekent het volle tarief voor een doorlopend lidmaatschap', function () {
    maakLid('Door', '2025-03-01');

    $lines = $this->run->preview(2026);

    expect($lines)->toHaveCount(1)
        ->and($lines->first()->isHalfRate)->toBeFalse()
        ->and($lines->first()->amount)->toBe('120.00');
});

it('rekent het volle tarief bij instroom in de eerste helft van het verenigingsjaar', function () {
    maakLid('Vroeg', '2026-03-01');

    $lines = $this->run->preview(2026);

    expect($lines->first()->isHalfRate)->toBeFalse()
        ->and($lines->first()->amount)->toBe('120.00');
});

it('rekent het halve tarief bij instroom in de tweede helft van het verenigingsjaar', function () {
    maakLid('Laat', '2026-10-01');

    $lines = $this->run->preview(2026);

    expect($lines->first()->isHalfRate)->toBeTrue()
        ->and($lines->first()->amount)->toBe('60.00');
});

it('maakt charges aan voor de run en boekt ze via BillingService', function () {
    $membership = maakLid('Nieuw', '2026-03-01');

    $result = $this->run->run(2026);

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($result['total'])->toBe('120.00');

    $charge = Charge::query()->where('subject_type', Membership::class)->where('subject_id', $membership->id)->firstOrFail();
    expect((float) $charge->amount)->toBe(120.0)
        ->and($charge->period)->toBe('2026');
});

it('slaat een lidmaatschap over waarvoor dit jaar al een post bestaat', function () {
    maakLid('Herhaal', '2026-03-01');

    $eerste = $this->run->run(2026);
    $tweede = $this->run->run(2026);

    expect($eerste['created'])->toBe(1)
        ->and($tweede['created'])->toBe(0)
        ->and($tweede['skipped'])->toBe(1)
        ->and(Charge::query()->count())->toBe(1);
});

it('negeert een lidmaatschapsvorm zonder contributie-product', function () {
    $zonderProduct = MembershipType::create(['key' => 'zonder-product', 'name' => 'Zonder product']);
    $person = Person::create(['first_name' => 'Geen', 'last_name' => 'Product']);
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $zonderProduct->id,
        'status' => MembershipStatus::Active,
        'start_date' => '2026-03-01',
        'billing_person_id' => $person->id,
    ]);

    expect($this->run->preview(2026))->toHaveCount(0);
});

it('negeert een niet-actief lidmaatschap', function () {
    maakLid('Opgezegd', '2025-03-01', MembershipStatus::Cancelled);
    maakLid('Aanvraag', '2026-03-01', MembershipStatus::Pending);

    expect($this->run->preview(2026))->toHaveCount(0);
});
