<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\EnrollmentLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Een reeks activiteiten (voorkomens) die bij elkaar horen, op aaneensluitende
 * of losse data (§17.3/17.4). Los van `ActivityPage` (§17.5): een reeks
 * genereert/beheert de voorkomens zelf en bepaalt het inschrijfniveau; een
 * activiteitenpagina is alleen een optionele CMS-infopagina waar willekeurige
 * losse activiteiten naar kunnen verwijzen.
 *
 * @property int $id
 * @property int $activity_category_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property int|null $default_capacity
 * @property int|null $min_capacity
 * @property int|null $min_age
 * @property int|null $max_age
 * @property Carbon|null $publish_from
 * @property Carbon|null $publish_until
 * @property EnrollmentLevel $enrollment_level
 * @property ActivityVisibility $visibility
 * @property ActivityStatus $status
 * @property int|null $split_from_id
 * @property int|null $created_by_person_id
 * @property-read ActivityCategory $category
 * @property-read ActivitySeries|null $splitFrom
 * @property-read Collection<int, ActivitySeries> $splits
 * @property-read Person|null $createdBy
 * @property-read Collection<int, Activity> $activities
 */
class ActivitySeries extends Model
{
    protected $fillable = [
        'activity_category_id',
        'title',
        'description',
        'location',
        'default_capacity',
        'min_capacity',
        'min_age',
        'max_age',
        'publish_from',
        'publish_until',
        'enrollment_level',
        'visibility',
        'status',
        'split_from_id',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'publish_from' => 'datetime',
            'publish_until' => 'datetime',
            'default_capacity' => 'int',
            'min_capacity' => 'int',
            'min_age' => 'int',
            'max_age' => 'int',
            'enrollment_level' => EnrollmentLevel::class,
            'visibility' => ActivityVisibility::class,
            'status' => ActivityStatus::class,
        ];
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'activity_category_id');
    }

    /** @return BelongsTo<ActivitySeries, $this> */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_id');
    }

    /** @return HasMany<ActivitySeries, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'series_id')->orderBy('starts_at');
    }

    /**
     * Voorkomens die nog niet losgekoppeld zijn van serie-brede wijzigingen.
     *
     * @return HasMany<Activity, $this>
     */
    public function nonExceptionActivities(): HasMany
    {
        return $this->activities()->where('is_exception', false);
    }

    public function isWithinPublishWindow(?Carbon $moment = null): bool
    {
        $moment ??= Carbon::now();

        if ($this->publish_from !== null && $moment->lt($this->publish_from)) {
            return false;
        }

        if ($this->publish_until !== null && $moment->gt($this->publish_until)) {
            return false;
        }

        return true;
    }
}
