<?php

namespace App\Models;

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'change_type',
        'payload',
        'proposed_by_person_id',
        'status',
        'policy_id',
        'current_step',
        'decision_reason',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ProposalStatus::class,
            'change_type' => ChangeType::class,
            'current_step' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'proposed_by_person_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ReviewPolicy::class, 'policy_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ReviewStep::class)->orderBy('sequence');
    }
}
