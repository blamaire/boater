<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string|null $last_name_prefix
 * @property string $last_name
 * @property Carbon|null $date_of_birth
 * @property string|null $email
 * @property string|null $phone
 * @property int|null $household_id
 * @property int|null $account_id
 * @property string|null $ecaptain_id
 * @property string|null $status
 * @property-read Household|null $household
 * @property-read User|null $account
 */
class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'first_name',
        'last_name_prefix',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'household_id',
        'account_id',
        'ecaptain_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_assignments')
            ->withPivot(['status', 'assigned_at', 'ends_at', 'deactivated_at'])
            ->withTimestamps();
    }

    public function personPermissions(): HasMany
    {
        return $this->hasMany(PersonPermission::class);
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'person_permissions')
            ->withPivot(['status', 'ends_at'])
            ->withTimestamps();
    }

    public function approverGroups(): BelongsToMany
    {
        return $this->belongsToMany(ApproverGroup::class, 'group_members', 'person_id', 'group_id')
            ->withTimestamps();
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Guardianship, $this> */
    public function asMinorGuardianships(): HasMany
    {
        return $this->hasMany(Guardianship::class, 'minor_person_id');
    }

    /** @return HasMany<Guardianship, $this> */
    public function asGuardianGuardianships(): HasMany
    {
        return $this->hasMany(Guardianship::class, 'guardian_person_id');
    }

    /** @return HasMany<PersonFieldVisibility, $this> */
    public function fieldVisibilities(): HasMany
    {
        return $this->hasMany(PersonFieldVisibility::class);
    }

    /** @return HasMany<IceContact, $this> */
    public function iceContacts(): HasMany
    {
        return $this->hasMany(IceContact::class);
    }

    /**
     * Relaties waarin deze persoon de subject is (bv. ouder van kind X):
     * `$parent->relations` levert de PersonRelation-records met
     * $relation->related_person = het kind.
     *
     * @return HasMany<PersonRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(PersonRelation::class, 'person_id');
    }

    /**
     * Relaties waarin deze persoon het object is (bv. jeugdlid dat een ouder
     * heeft): `$child->inverseRelations->first()->person` = de ouder.
     *
     * @return HasMany<PersonRelation, $this>
     */
    public function inverseRelations(): HasMany
    {
        return $this->hasMany(PersonRelation::class, 'related_person_id');
    }

    /**
     * Het huidige lopende lidmaatschap (actief, en einddatum leeg of in de
     * toekomst). Wordt gebruikt door "Mijn lidmaatschap" (§19.1).
     */
    public function currentMembership(): ?Membership
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Is deze persoon nu een actief lid? Basis voor
     * membership-afgeleide permissies (§6-7).
     */
    public function hasActiveMembership(): bool
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->exists();
    }

    public function fullName(): string
    {
        return trim(collect([$this->first_name, $this->last_name_prefix, $this->last_name])->filter()->implode(' '));
    }
}
