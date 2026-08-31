<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case Telefoon = 'telefoon';
    case Email = 'email';
    case Social = 'social';
    case Gesprek = 'gesprek';
    case Brief = 'brief';

    public function label(): string
    {
        return match ($this) {
            self::Telefoon => 'Telefoon',
            self::Email => 'E-mail',
            self::Social => 'Social media',
            self::Gesprek => 'Gesprek',
            self::Brief => 'Brief',
        };
    }
}
