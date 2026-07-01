<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $person_id
 * @property int $role_id
 * @property string $status
 * @property int|null $assigned_by
 * @property Carbon|null $assigned_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $deactivated_at
 * @property string|null $reason
 * @property-read Person $person
 * @property-read Role|null $role
 * @property-read Person|null $assignedBy
 */
class RoleAssignment extends Model
{
    protected $fillable = [
        'person_id',
        'role_id',
        'status',
        'assigned_by',
        'assigned_at',
        'ends_at',
        'deactivated_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ends_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_by');
    }
}
