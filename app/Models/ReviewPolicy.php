<?php

namespace App\Models;

use App\Enums\ResubmitBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
