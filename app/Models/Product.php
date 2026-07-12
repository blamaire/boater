<?php

namespace App\Models;

use App\Enums\ProductRecurrence;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ProductType $type
 * @property int|null $ledger_account_id
 * @property bool $is_recurring
 * @property ProductRecurrence|null $recurrence
 * @property-read LedgerAccount|null $ledgerAccount
 * @property-read Collection<int, ProductPrice> $prices
 * @property-read Collection<int, MembershipType> $membershipTypes
 */
class Product extends Model
{
    protected $fillable = [
        'name',
        'type',
        'ledger_account_id',
        'is_recurring',
        'recurrence',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'recurrence' => ProductRecurrence::class,
            'is_recurring' => 'bool',
        ];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderByDesc('valid_from');
    }

    /** @return HasMany<MembershipType, $this> */
    public function membershipTypes(): HasMany
    {
        return $this->hasMany(MembershipType::class);
    }

    /**
     * De prijs die op de gegeven datum geldt: de meest recente `valid_from`
     * die niet in de toekomst ligt. Null als er (nog) geen prijs vóór die
     * datum bestaat.
     */
    public function priceOn(Carbon $date): ?ProductPrice
    {
        return $this->prices()
            ->where('valid_from', '<=', $date)
            ->orderByDesc('valid_from')
            ->first();
    }

    public function currentPrice(): ?ProductPrice
    {
        return $this->priceOn(Carbon::now());
    }

    /**
     * De eerstvolgende prijs die nog moet ingaan (valid_from in de toekomst).
     * Handig om te tonen dat er wél een prijs is vastgelegd, maar pas later
     * geldt — anders lijkt de huidige prijs "leeg".
     */
    public function upcomingPrice(): ?ProductPrice
    {
        return $this->prices()
            ->where('valid_from', '>', Carbon::now())
            ->orderBy('valid_from')
            ->first();
    }
}
