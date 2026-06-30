<?php

namespace App\Models;

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject_type
 * @property int|null $subject_id
 * @property ChangeType $change_type
 * @property array<string, mixed> $payload
 * @property int $proposed_by_person_id
 * @property ProposalStatus $status
 * @property int|null $policy_id
 * @property int $current_step
 * @property string|null $decision_reason
 * @property Carbon|null $applied_at
 * @property ReviewPolicy|null $policy
 * @property-read Person $proposedBy
 * @property-read Collection<int, ReviewStep> $steps
 */
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

    /** @return BelongsTo<Person, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'proposed_by_person_id');
    }

    /** @return BelongsTo<ReviewPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(ReviewPolicy::class, 'policy_id');
    }

    /** @return HasMany<ReviewStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ReviewStep::class)->orderBy('sequence');
    }
}
