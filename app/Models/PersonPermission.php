<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonPermission extends Model
{
    protected $fillable = [
        'person_id',
        'permission_id',
        'status',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
