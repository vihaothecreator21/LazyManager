<?php

namespace App\Domain\Enums;

enum UserRole: string
{
    case StoreManager = 'STORE_MANAGER';
    case Staff = 'STAFF';
}
