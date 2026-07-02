<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $person_id
 * @property int $membership_type_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property MembershipStatus $status
 * @property int|null $derives_from_membership_id
 * @property int|null $billing_person_id
 * @property-read Person $person
 * @property-read MembershipType $type
 * @property-read Person|null $billingPerson
 * @property-read Membership|null $derivesFrom
 */
class Membership extends Model
{
    protected $fillable = [
        'person_id',
        'membership_type_id',
        'start_date',
        'end_date',
        'status',
        'derives_from_membership_id',
        'billing_person_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => MembershipStatus::class,
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<MembershipType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function billingPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'billing_person_id');
    }

    /** @return BelongsTo<Membership, $this> */
    public function derivesFrom(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'derives_from_membership_id');
    }
}
