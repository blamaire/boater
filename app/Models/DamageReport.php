<?php

namespace App\Models;

use App\Enums\DamageReportStatus;
use App\Enums\DamageSeverity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reservable_object_id
 * @property int $reported_by_person_id
 * @property int|null $reservation_id
 * @property string $description
 * @property DamageSeverity $severity
 * @property bool $reporter_marked_unusable
 * @property DamageReportStatus $status
 * @property Carbon $reported_at
 * @property int|null $assigned_to_person_id
 * @property string|null $resolution
 * @property Carbon|null $resolved_at
 * @property-read ReservableObject $object
 * @property-read Person $reportedBy
 * @property-read Person|null $assignedTo
 * @property-read Reservation|null $reservation
 * @property-read Collection<int, MediaAsset> $photos
 */
class DamageReport extends Model
{
    protected $fillable = [
        'reservable_object_id',
        'reported_by_person_id',
        'reservation_id',
        'description',
        'severity',
        'reporter_marked_unusable',
        'status',
        'reported_at',
        'assigned_to_person_id',
        'resolution',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => DamageSeverity::class,
            'status' => DamageReportStatus::class,
            'reporter_marked_unusable' => 'bool',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReservableObject, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(ReservableObject::class, 'reservable_object_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reported_by_person_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to_person_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsToMany<MediaAsset, $this> */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'damage_report_media')
            ->withTimestamps();
    }
}
