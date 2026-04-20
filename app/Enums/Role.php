<?php

namespace App\Enums;

enum Role: string
{
    case Service = 'service';
    case Admin = 'admin';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Admin => 'Admin',
            self::Student => 'Student',
        };
    }

    public function canManageUsers(): bool
    {
        return $this === self::Service;
    }

    public function canViewAllScholars(): bool
    {
        return $this === self::Service || $this === self::Admin;
    }
}
