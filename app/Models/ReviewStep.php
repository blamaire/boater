<?php

namespace App\Models;

use App\Enums\AssigneeType;
use App\Enums\ReviewStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
