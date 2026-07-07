<?php

namespace App\Models;

use App\Enums\ReservableObjectStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $object_category_id
 * @property string $name
 * @property string|null $location
 * @property array<string, mixed>|null $attributes
 * @property ReservableObjectStatus $status
 * @property-read ObjectCategory $category
 * @property-read Collection<int, Reservation> $reservations
 */
class ReservableObject extends Model
{
    protected $fillable = [
        'object_category_id',
        'name',
        'location',
        'attributes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'status' => ReservableObjectStatus::class,
        ];
    }

    /** @return BelongsTo<ObjectCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ObjectCategory::class, 'object_category_id');
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === ReservableObjectStatus::Available;
    }
}
