<?php

namespace App\Models;

use Database\Factories\ProjectMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academic_year', 'redcap_pid', 'redcap_token', 'is_active'])]
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
            'redcap_pid' => 'integer',
            'redcap_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The single active source project mapping. There should be at most one row
     * with is_active = true. Falls back to the most recently created mapping if
     * none has been explicitly activated.
     */
    public static function activeSource(): ?self
    {
        return self::query()->where('is_active', true)->first()
            ?? self::query()->orderByDesc('id')->first();
    }

    public static function current(): ?self
    {
        return self::activeSource();
    }

    public static function latestSourceProject(): ?self
    {
        return self::activeSource();
    }

    public function displayName(): string
    {
        return "Source Project (PID {$this->redcap_pid})";
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
