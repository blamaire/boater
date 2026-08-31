<?php

namespace App\Enums;

enum MessageType: string
{
    case Transactioneel = 'transactioneel';
    case Redactioneel = 'redactioneel';

    public function label(): string
    {
        return match ($this) {
            self::Transactioneel => 'Transactioneel',
            self::Redactioneel => 'Redactioneel',
        };
    }
}
