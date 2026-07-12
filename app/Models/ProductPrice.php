<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property Carbon $valid_from
 * @property string $amount
 * @property-read Product $product
 */
class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'valid_from',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
