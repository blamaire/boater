<?php

namespace App\Services\Proposals;

use App\Services\Proposals\Contracts\ProposalHandler;
use InvalidArgumentException;

class ProposalHandlerRegistry
{
    /** @var array<string, ProposalHandler> */
    private array $handlers = [];

    public function register(string $subjectType, ProposalHandler $handler): void
    {
        $this->handlers[$subjectType] = $handler;
    }

    public function has(string $subjectType): bool
    {
        return isset($this->handlers[$subjectType]);
    }

    public function for(string $subjectType): ProposalHandler
    {
        if (! isset($this->handlers[$subjectType])) {
            throw new InvalidArgumentException("Geen handler geregistreerd voor subject_type [{$subjectType}].");
        }

        return $this->handlers[$subjectType];
    }
}
