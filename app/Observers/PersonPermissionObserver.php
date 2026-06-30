<?php

namespace App\Observers;

use App\Models\PersonPermission;
use App\Services\Audit\AuditLogger;

class PersonPermissionObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(PersonPermission $permission): void
    {
        $this->audit->log(
            action: 'person_permission.granted',
            subject: $permission,
            after: $this->snapshot($permission),
            context: [
                'person_id' => $permission->person_id,
                'permission_id' => $permission->permission_id,
            ],
        );
    }

    public function updated(PersonPermission $permission): void
    {
        if (! $permission->wasChanged('status')) {
            return;
        }

        $previousStatus = $permission->getOriginal('status');

        $this->audit->log(
            action: 'person_permission.status_changed',
            subject: $permission,
            before: ['status' => $previousStatus],
            after: ['status' => $permission->status],
            context: [
                'person_id' => $permission->person_id,
                'permission_id' => $permission->permission_id,
            ],
        );
    }

    private function snapshot(PersonPermission $permission): array
    {
        return [
            'status' => $permission->status,
            'ends_at' => $permission->ends_at?->toIso8601String(),
        ];
    }
}
