<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['email', 'name', 'role', 'okta_nameid', 'redcap_record_id', 'public_token', 'last_login_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->public_token)) {
                $user->public_token = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function isService(): bool
    {
        return $this->role === Role::Service;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    public function canManageUsers(): bool
    {
        return $this->role?->canManageUsers() ?? false;
    }

    public function canViewAllScholars(): bool
    {
        return $this->role?->canViewAllScholars() ?? false;
    }
}
