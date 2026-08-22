<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case Nieuw = 'nieuw';
    case InBehandeling = 'in_behandeling';
    case Afgehandeld = 'afgehandeld';

    public function label(): string
    {
        return match ($this) {
            self::Nieuw => 'Nieuw',
            self::InBehandeling => 'In behandeling',
            self::Afgehandeld => 'Afgehandeld',
        };
    }
}
