<?php

namespace App\Models;

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ReviewStepStatus;
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
 * @property int|null $proposed_by_person_id
 * @property ProposalStatus $status
 * @property int|null $policy_id
 * @property int $current_step
 * @property string|null $decision_reason
 * @property Carbon|null $applied_at
 * @property Carbon|null $archived_at
 * @property ReviewPolicy|null $policy
 * @property-read Person|null $proposedBy
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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ProposalStatus::class,
            'change_type' => ChangeType::class,
            'current_step' => 'integer',
            'applied_at' => 'datetime',
            'archived_at' => 'datetime',
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

    /**
     * De stap die op dit moment daadwerkelijk beslist kan worden (§20.4 —
     * stappen worden sequentieel doorlopen, ook al staan latere stappen al
     * als 'pending' in de database).
     */
    public function currentStep(): ?ReviewStep
    {
        return $this->steps()
            ->where('sequence', $this->current_step)
            ->where('status', ReviewStepStatus::Pending)
            ->first();
    }

    /**
     * Afgewezen, maar nog niet door de indiener gearchiveerd — moet actief
     * zichtbaar blijven bij "Mijn voorstellen" totdat het lid kiest tussen
     * opnieuw indienen en archiveren.
     */
    public function needsRejectionAction(): bool
    {
        return $this->status === ProposalStatus::Rejected && $this->archived_at === null;
    }
}
