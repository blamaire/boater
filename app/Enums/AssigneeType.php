<?php

namespace App\Enums;

enum AssigneeType: string
{
    case Role = 'role';
    case Group = 'group';
    case Person = 'person';
}
