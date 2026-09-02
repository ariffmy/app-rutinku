<?php

namespace App\Enums;

enum UserRole: string
{
    case PARENT = 'parent';
    case CHILD = 'child';
}
