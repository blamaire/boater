<?php

namespace App\Observers;

use App\Models\RoleAssignment;
use App\Services\Audit\AuditLogger;

class RoleAssignmentObserver
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function created(RoleAssignment $assignment): void
    {
        $this->audit->log(
            action: 'role.assigned',
            subject: $assignment,
            after: $this->snapshot($assignment),
            context: [
                'person_id' => $assignment->person_id,
                'role_id' => $assignment->role_id,
            ],
        );
    }

    public function updated(RoleAssignment $assignment): void
    {
        if (! $assignment->wasChanged('status')) {
            return;
        }

        $previousStatus = $assignment->getOriginal('status');

        $this->audit->log(
            action: 'role.status_changed',
            subject: $assignment,
            before: ['status' => $previousStatus],
            after: ['status' => $assignment->status],
            context: [
                'person_id' => $assignment->person_id,
                'role_id' => $assignment->role_id,
            ],
        );
    }

    private function snapshot(RoleAssignment $assignment): array
    {
        return [
            'status' => $assignment->status,
            'assigned_by' => $assignment->assigned_by,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'ends_at' => $assignment->ends_at?->toIso8601String(),
        ];
    }
}
