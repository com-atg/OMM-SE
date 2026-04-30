<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['email', 'name', 'role', 'okta_nameid', 'redcap_record_id', 'public_token', 'last_login_at', 'cohort_start_term', 'cohort_start_year', 'batch', 'is_active'])]
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
            'cohort_start_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    public function isFaculty(): bool
    {
        return $this->role === Role::Faculty;
    }

    public function canManageUsers(): bool
    {
        return $this->isService();
    }

    public function canViewSettings(): bool
    {
        return $this->isService() || $this->isAdmin();
    }

    public function canManageSettingsRecords(): bool
    {
        return $this->isService();
    }

    public function canEditEmailTemplate(): bool
    {
        return $this->isService() || $this->isAdmin();
    }

    public function canViewAllStudents(): bool
    {
        return $this->isService() || $this->isAdmin();
    }

    public function canViewDashboard(): bool
    {
        return $this->canViewAllStudents() || $this->isFaculty();
    }

    public function canViewFacultyDetail(): bool
    {
        return $this->canViewAllStudents() || $this->isFaculty();
    }
}
