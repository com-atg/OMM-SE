<?php

namespace App\Models;

use Database\Factories\ProjectMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academic_year', 'graduation_year', 'redcap_pid', 'redcap_token'])]
class ProjectMapping extends Model
{
    /** @use HasFactory<ProjectMappingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'redcap_pid' => 'integer',
            'redcap_token' => 'encrypted',
        ];
    }

    public function categoryWeights(): HasMany
    {
        return $this->hasMany(CategoryWeight::class);
    }

    public static function current(): ?self
    {
        return self::query()
            ->orderByDesc('graduation_year')
            ->orderByDesc('academic_year')
            ->first();
    }

    public function displayName(): string
    {
        return "Academic Year {$this->academic_year} (Class of {$this->graduation_year})";
    }

    public function maskedToken(): string
    {
        $token = (string) $this->redcap_token;

        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 4).substr($token, -4);
    }
}
