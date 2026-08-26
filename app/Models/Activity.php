<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_category_id
 * @property int|null $activity_page_id
 * @property int|null $series_id
 * @property bool $is_exception
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property int|null $capacity
 * @property int|null $min_capacity
 * @property int|null $min_age
 * @property int|null $max_age
 * @property Carbon|null $publish_from
 * @property Carbon|null $publish_until
 * @property Carbon|null $enrollment_opens_at
 * @property Carbon|null $enrollment_closes_at
 * @property Carbon|null $cancellation_deadline
 * @property int|null $standard_cost_product_id
 * @property int|null $cancellation_cost_product_id
 * @property ActivityVisibility $visibility
 * @property ActivityStatus $status
 * @property int|null $created_by_person_id
 * @property-read ActivityCategory $category
 * @property-read ActivityPage|null $activityPage
 * @property-read ActivitySeries|null $series
 * @property-read Person|null $createdBy
 * @property-read Product|null $standardCostProduct
 * @property-read Product|null $cancellationCostProduct
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Person> $managers
 * @property-read Collection<int, ApproverGroup> $managerGroups
 * @property-read Collection<int, MediaAsset> $files
 * @property-read Collection<int, ActivityRegistrationField> $registrationFields
 */
class Activity extends Model
{
    protected $fillable = [
        'activity_category_id',
        'activity_page_id',
        'series_id',
        'is_exception',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'capacity',
        'min_capacity',
        'min_age',
        'max_age',
        'publish_from',
        'publish_until',
        'enrollment_opens_at',
        'enrollment_closes_at',
        'cancellation_deadline',
        'standard_cost_product_id',
        'cancellation_cost_product_id',
        'visibility',
        'status',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_exception' => 'bool',
            'capacity' => 'int',
            'min_capacity' => 'int',
            'min_age' => 'int',
            'max_age' => 'int',
            'publish_from' => 'datetime',
            'publish_until' => 'datetime',
            'enrollment_opens_at' => 'datetime',
            'enrollment_closes_at' => 'datetime',
            'cancellation_deadline' => 'datetime',
            'visibility' => ActivityVisibility::class,
            'status' => ActivityStatus::class,
        ];
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'activity_category_id');
    }

    /** @return BelongsTo<ActivityPage, $this> */
    public function activityPage(): BelongsTo
    {
        return $this->belongsTo(ActivityPage::class);
    }

    /** @return BelongsTo<ActivitySeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(ActivitySeries::class);
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
     * Gedelegeerde beheerders (§6): zien inschrijvingen en mogen wijzigen
     * zonder de globale `activities.update`-permissie. `notify` op de pivot
     * bepaalt of deze beheerder mailnotificaties wil bij wijzigingen/in-
     * en uitschrijvingen.
     *
     * @return BelongsToMany<Person, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'activity_managers')
            ->withPivot('notify')
            ->withTimestamps();
    }

    /**
     * Hele goedkeuringsgroepen als gedelegeerd beheerder: alle huidige én
     * toekomstige leden van de groep gelden als beheerder, naast de losse
     * personen in `managers()`.
     *
     * @return BelongsToMany<ApproverGroup, $this>
     */
    public function managerGroups(): BelongsToMany
    {
        return $this->belongsToMany(ApproverGroup::class, 'activity_manager_groups')
            ->withPivot('notify')
            ->withTimestamps();
    }

    /** @return BelongsToMany<MediaAsset, $this> */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'activity_media')
            ->withTimestamps();
    }

    /**
     * Extra inschrijfvelden (§17.3/17.4, Fase C).
     *
     * @return HasMany<ActivityRegistrationField, $this>
     */
    public function registrationFields(): HasMany
    {
        return $this->hasMany(ActivityRegistrationField::class)->orderBy('sort_order');
    }

    /**
     * Fase D: het bedrag komt uit de actuele prijs (`Product::currentPrice()`)
     * van dit product, niet uit een los bedrag hier.
     *
     * @return BelongsTo<Product, $this>
     */
    public function standardCostProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'standard_cost_product_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function cancellationCostProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cancellation_cost_product_id');
    }

    public function isManagedBy(?Person $person): bool
    {
        if ($person === null) {
            return false;
        }

        if ($this->managers->contains('id', $person->id)) {
            return true;
        }

        return $this->managerGroups->contains(
            fn (ApproverGroup $group): bool => $group->members->contains('id', $person->id)
        );
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

    public function isBelowMinimum(): bool
    {
        if ($this->min_capacity === null) {
            return false;
        }

        return $this->enrolledCount() < $this->min_capacity;
    }

    /**
     * Publiek zichtbaar op basis van het publicatievenster (los van
     * `visibility`, die bepaalt wíé het mag zien als het venster open is).
     */
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

    /**
     * Los van het publicatievenster: mag er nu ingeschreven worden? Zonder
     * `enrollment_opens_at`/`enrollment_closes_at` staat inschrijven altijd
     * open (§17.4, Fase B).
     */
    public function isEnrollmentOpen(?Carbon $moment = null): bool
    {
        $moment ??= Carbon::now();

        if ($this->enrollment_opens_at !== null && $moment->lt($this->enrollment_opens_at)) {
            return false;
        }

        if ($this->enrollment_closes_at !== null && $moment->gt($this->enrollment_closes_at)) {
            return false;
        }

        return true;
    }

    /**
     * Mag een bestaande inschrijving nu nog geannuleerd worden? Zonder
     * `cancellation_deadline` mag dat altijd.
     */
    public function canCancel(?Carbon $moment = null): bool
    {
        $moment ??= Carbon::now();

        return $this->cancellation_deadline === null || $moment->lte($this->cancellation_deadline);
    }

    public function isAgeEligible(Person $person): bool
    {
        if ($person->date_of_birth === null) {
            return true;
        }

        $age = $person->date_of_birth->diffInYears($this->starts_at);

        if ($this->min_age !== null && $age < $this->min_age) {
            return false;
        }

        if ($this->max_age !== null && $age > $this->max_age) {
            return false;
        }

        return true;
    }
}
