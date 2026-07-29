<?php

namespace App\Domain\Enums;

enum UserStatus: string
{
    case Active = 'ACTIVE';
    case Locked = 'LOCKED';
}
