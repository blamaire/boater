<?php

namespace App\Enums;

enum CommunicationDirection: string
{
    case In = 'in';
    case Uit = 'uit';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Inkomend',
            self::Uit => 'Uitgaand',
        };
    }
}
