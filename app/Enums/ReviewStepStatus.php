<?php

namespace App\Enums;

enum ReviewStepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Skipped = 'skipped';
}
