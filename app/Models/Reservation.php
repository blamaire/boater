<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reservable_object_id
 * @property int $person_id
 * @property int|null $requested_by_person_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ReservationStatus $status
 * @property string|null $note
 * @property-read ReservableObject $object
 * @property-read Person $person
 * @property-read Person|null $requestedBy
 */
class Reservation extends Model
{
    protected $fillable = [
        'reservable_object_id',
        'person_id',
        'requested_by_person_id',
        'starts_at',
        'ends_at',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ReservationStatus::class,
        ];
    }

    /** @return BelongsTo<ReservableObject, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(ReservableObject::class, 'reservable_object_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requested_by_person_id');
    }
}
