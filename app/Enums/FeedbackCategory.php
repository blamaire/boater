<?php

namespace App\Enums;

enum FeedbackCategory: string
{
    case Bug = 'bug';
    case Suggestie = 'suggestie';
    case Vraag = 'vraag';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Bug',
            self::Suggestie => 'Suggestie',
            self::Vraag => 'Vraag',
            self::Overig => 'Overig',
        };
    }
}
