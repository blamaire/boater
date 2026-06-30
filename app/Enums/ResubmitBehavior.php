<?php

namespace App\Enums;

enum ResubmitBehavior: string
{
    case Restart = 'restart';
    case Resume = 'continue';
}
