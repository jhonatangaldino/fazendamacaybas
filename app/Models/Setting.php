<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type', 'label', 'description', 'order_column', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'order_column' => 'integer',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting.{$key}";

        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'int' => (int) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', string $group = 'geral'): self
    {
        $stored = match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };

        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group]
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => Cache::forget("setting.{$s->key}"));
        static::deleted(fn (Setting $s) => Cache::forget("setting.{$s->key}"));
    }
}
