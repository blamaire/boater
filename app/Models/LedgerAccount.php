<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LedgerAccountType $type
 * @property int|null $verdichting_id
 * @property-read Verdichting|null $verdichting
 * @property-read Collection<int, Product> $products
 */
class LedgerAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'verdichting_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerAccountType::class,
        ];
    }

    /** @return BelongsTo<Verdichting, $this> */
    public function verdichting(): BelongsTo
    {
        return $this->belongsTo(Verdichting::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
