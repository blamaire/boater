<?php

namespace App\Enums;

enum PageVisibility: string
{
    case Public = 'publiek';
    case Members = 'leden';
    case Restricted = 'beperkt';
}
