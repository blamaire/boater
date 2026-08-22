<?php

namespace App\Enums;

enum ContactPreferredMethod: string
{
    case Bellen = 'bellen';
    case Mailen = 'mailen';

    public function label(): string
    {
        return match ($this) {
            self::Bellen => 'Bel me terug',
            self::Mailen => 'Mail me terug',
        };
    }
}
