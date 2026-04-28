<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));
    }

    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();
        Cache::forget(self::cacheKey($key));
    }

    protected static function cacheKey(string $key): string
    {
        return 'app_setting:'.$key;
    }
}
