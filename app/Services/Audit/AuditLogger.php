<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?array $context = null,
    ): AuditEntry {
        return AuditEntry::create([
            'actor_person_id' => $this->resolveActor()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'context' => $context,
            'occurred_at' => Carbon::now(),
            'ip' => $this->resolveIp(),
            'user_agent' => $this->resolveUserAgent(),
        ]);
    }

    private function resolveActor(): ?Person
    {
        if (! Auth::check()) {
            return null;
        }

        return Auth::user()->person;
    }

    private function resolveIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->ip();
    }

    private function resolveUserAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->userAgent();
    }
}
