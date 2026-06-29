<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ends_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_by');
    }
}
