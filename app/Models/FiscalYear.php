<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $year
 * @property-read Collection<int, Period> $periods
 */
class FiscalYear extends Model
{
    protected $fillable = [
        'year',
    ];

    /** @return HasMany<Period, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }
}
