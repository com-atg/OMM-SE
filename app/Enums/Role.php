<?php

namespace App\Enums;

enum Role: string
{
    case Service = 'service';
    case Admin = 'admin';
    case Student = 'student';
    case Faculty = 'faculty';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Admin => 'Admin',
            self::Student => 'Student',
            self::Faculty => 'Faculty',
        };
    }

    public function canManageUsers(): bool
    {
        return $this === self::Service;
    }

    public function canViewAllStudents(): bool
    {
        return $this === self::Service || $this === self::Admin;
    }

    public function canViewDashboard(): bool
    {
        return $this !== self::Student;
    }

    public function canViewFacultyDetail(): bool
    {
        return $this === self::Service || $this === self::Admin || $this === self::Faculty;
    }
}
