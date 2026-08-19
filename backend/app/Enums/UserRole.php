<?php

namespace App\Enums;

enum UserRole: string
{
    case Accountant = 'accountant';
    case Owner = 'owner';
    case Parent = 'parent';
}
