<?php

namespace App\Models;

use App\Enums\AssigneeType;
use App\Enums\ReviewStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $proposal_id
 * @property int $sequence
 * @property AssigneeType $assignee_type
 * @property int $assignee_id
 * @property ReviewStepStatus $status
 * @property int|null $decided_by_person_id
 * @property Carbon|null $decided_at
 * @property string|null $reason
 * @property Carbon|null $due_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $escalated_at
 * @property-read Proposal $proposal
 * @property-read Person|null $decidedBy
 */
class ReviewStep extends Model
{
    protected $fillable = [
        'proposal_id',
        'sequence',
        'assignee_type',
        'assignee_id',
        'status',
        'decided_by_person_id',
        'decided_at',
        'reason',
        'due_at',
        'reminder_sent_at',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'assignee_id' => 'integer',
            'assignee_type' => AssigneeType::class,
            'status' => ReviewStepStatus::class,
            'decided_at' => 'datetime',
            'due_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'decided_by_person_id');
    }

    /**
     * Leesbare naam van de toegewezene, ongeacht assignee_type — voor
     * weergave in "wacht op ..." zonder dat de view zelf Role/ApproverGroup/
     * Person moet opzoeken.
     */
    public function assigneeName(): string
    {
        return match ($this->assignee_type) {
            AssigneeType::Person => Person::query()->find($this->assignee_id)?->fullName() ?? 'Onbekende persoon',
            AssigneeType::Role => Role::query()->whereKey($this->assignee_id)->value('name') ?? 'Onbekende rol',
            AssigneeType::Group => ApproverGroup::query()->whereKey($this->assignee_id)->value('name') ?? 'Onbekende groep',
        };
    }
}
