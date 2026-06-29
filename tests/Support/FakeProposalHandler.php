<?php

namespace Tests\Support;

use App\Models\Proposal;
use App\Services\Proposals\Contracts\ProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;

class FakeProposalHandler implements ProposalHandler
{
    /** @var array<int, int> proposal-id → apply-aanroepen */
    public array $applied = [];

    public bool $throwConflictOnRevalidate = false;

    public string $conflictMessage = 'conflict';

    public function revalidate(Proposal $proposal): void
    {
        if ($this->throwConflictOnRevalidate) {
            throw new ProposalConflictException($this->conflictMessage);
        }
    }

    public function apply(Proposal $proposal): void
    {
        $this->applied[$proposal->id] = ($this->applied[$proposal->id] ?? 0) + 1;
    }
}
