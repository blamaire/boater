<?php

namespace App\Models;

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

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

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
}
