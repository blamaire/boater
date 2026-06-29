<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_person_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'context',
        'occurred_at',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('AuditEntry is append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new \LogicException('AuditEntry is append-only and cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'actor_person_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
