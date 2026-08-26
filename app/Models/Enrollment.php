<?php

namespace App\Models;

use App\Enums\EnrollmentLevel;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_id
 * @property int|null $series_id
 * @property EnrollmentLevel $level
 * @property int $person_id
 * @property int|null $requested_by_person_id
 * @property EnrollmentStatus $status
 * @property Carbon $enrolled_at
 * @property-read Activity $activity
 * @property-read ActivitySeries|null $series
 * @property-read Person $person
 * @property-read Person|null $requestedBy
 * @property-read Collection<int, EnrollmentFieldValue> $fieldValues
 */
class Enrollment extends Model
{
    protected $fillable = [
        'activity_id',
        'series_id',
        'level',
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
            'level' => EnrollmentLevel::class,
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<ActivitySeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(ActivitySeries::class);
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

    /** @return HasMany<EnrollmentFieldValue, $this> */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(EnrollmentFieldValue::class);
    }

    /**
     * Indicatieve totaalprijs van de extra inschrijfvelden (Fase C — nog
     * geen echte Charge, dat volgt in Fase D).
     */
    public function indicativeFieldsTotal(): float
    {
        return $this->fieldValues->sum(fn (EnrollmentFieldValue $value): float => $value->indicativePrice());
    }
}
