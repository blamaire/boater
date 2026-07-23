<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Withdrawn = 'withdrawn';
    case Conflicted = 'conflicted';

    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::Submitted, self::InReview, self::Returned => true,
            default => false,
        };
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Concept',
            self::Submitted => 'Ingediend',
            self::InReview => 'In review',
            self::Approved => 'Goedgekeurd',
            self::Applied => 'Toegepast',
            self::Rejected => 'Afgewezen',
            self::Returned => 'Teruggestuurd',
            self::Withdrawn => 'Ingetrokken',
            self::Conflicted => 'Conflict',
        };
    }
}
