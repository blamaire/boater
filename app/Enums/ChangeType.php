<?php

namespace App\Enums;

enum ChangeType: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
