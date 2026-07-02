<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aanvraag',
            self::Active => 'Actief',
            self::Cancelled => 'Opgezegd',
            self::Expired => 'Vervallen',
            self::Rejected => 'Geweigerd',
        };
    }
}
