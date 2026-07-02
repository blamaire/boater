<?php

namespace App\Services\Membership;

use App\Models\MembershipType;

readonly class MembershipTypeEligibility
{
    public function __construct(
        public MembershipType $type,
        public bool $available,
        public ?string $reason,
    ) {}
}
