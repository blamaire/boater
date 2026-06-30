<?php

namespace App\Models;

use App\Enums\ResubmitBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $subject_type
 * @property array<string, mixed>|null $condition
 * @property bool $auto_apply
 * @property list<array{assignee_type: string, assignee_id: int}> $steps
 * @property string|null $bypass_permission
 * @property ResubmitBehavior $resubmit_behavior
 * @property int|null $reminder_after_days
 * @property int|null $escalation_after_days
 * @property int|null $escalation_group_id
 * @property-read ApproverGroup|null $escalationGroup
 */
class ReviewPolicy extends Model
{
    protected $fillable = [
        'name',
        'subject_type',
        'condition',
        'auto_apply',
        'steps',
        'bypass_permission',
        'resubmit_behavior',
        'reminder_after_days',
        'escalation_after_days',
        'escalation_group_id',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'auto_apply' => 'boolean',
            'steps' => 'array',
            'resubmit_behavior' => ResubmitBehavior::class,
            'reminder_after_days' => 'integer',
            'escalation_after_days' => 'integer',
        ];
    }

    public function escalationGroup(): BelongsTo
    {
        return $this->belongsTo(ApproverGroup::class, 'escalation_group_id');
    }
}
