<?php

namespace App\Enums;

enum PageVersionStatus: string
{
    case Draft = 'concept';
    case InReview = 'in_review';
    case Published = 'gepubliceerd';
    case Archived = 'gearchiveerd';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
