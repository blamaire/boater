<?php

namespace App\Enums;

enum BandLayout: int
{
    case OneColumn = 1;
    case TwoColumns = 2;
    case ThreeColumns = 3;

    public function columnCount(): int
    {
        return $this->value;
    }
}
