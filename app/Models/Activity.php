<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_category_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property int|null $capacity
 * @property ActivityVisibility $visibility
 * @property ActivityStatus $status
 * @property int|null $created_by_person_id
 * @property-read ActivityCategory $category
 * @property-read Person|null $createdBy
 * @property-read Collection<int, Enrollment> $enrollments
 */
class Activity extends Model
{
    protected $fillable = [
        'activity_category_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'capacity',
        'visibility',
        'status',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'int',
            'visibility' => ActivityVisibility::class,
            'status' => ActivityStatus::class,
        ];
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'activity_category_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Aantal aangemelde (geen wachtlijst, geen afgemeld) deelnemers.
     */
    public function enrolledCount(): int
    {
        return $this->enrollments()
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->count();
    }

    public function hasFreeSpot(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->enrolledCount() < $this->capacity;
    }
}
