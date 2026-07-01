<?php

namespace App\Enums;

enum PageType: string
{
    case Content = 'content';
    case System = 'systeem';

    public function isDeletable(): bool
    {
        return $this === self::Content;
    }
}
