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
}
