<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureSetting extends Model
{
    public const CACHE_KEY = 'feature_settings.map';

    /** Peta fitur untuk request yang sedang berjalan, agar cache tidak dibaca berulang. */
    private static ?array $memo = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flush());
        static::deleted(fn () => self::flush());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$memo = null;
    }

    /**
     * Peta feature_key => is_enabled. Dibaca dari cache; jika kosong, dari database.
     * Hasil kosong tidak pernah di-cache, supaya cache yang terlanjur kosong tidak memblokir semua fitur.
     */
    public static function map(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $map = Cache::get(self::CACHE_KEY);

        if (!is_array($map) || $map === []) {
            $map = static::query()->pluck('is_enabled', 'feature_key')
                ->map(fn ($v) => (bool) $v)
                ->all();

            if ($map !== []) {
                Cache::forever(self::CACHE_KEY, $map);
            }
        }

        return self::$memo = $map;
    }

    public static function isEnabled(string $key): bool
    {
        return self::map()[$key] ?? false;
    }
}
