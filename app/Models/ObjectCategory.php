<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $requires_boat_right
 * @property int $sort_order
 * @property-read Collection<int, ReservableObject> $objects
 */
class ObjectCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'requires_boat_right',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_boat_right' => 'bool',
            'sort_order' => 'int',
        ];
    }

    /** @return HasMany<ReservableObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(ReservableObject::class);
    }
}
