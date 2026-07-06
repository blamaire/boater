<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_id
 * @property int $person_id
 * @property int|null $requested_by_person_id
 * @property EnrollmentStatus $status
 * @property Carbon $enrolled_at
 * @property-read Activity $activity
 * @property-read Person $person
 * @property-read Person|null $requestedBy
 */
class Enrollment extends Model
{
    protected $fillable = [
        'activity_id',
        'person_id',
        'requested_by_person_id',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'status' => EnrollmentStatus::class,
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
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
