<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type', 'label', 'description', 'order_column', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'order_column' => 'integer',
    ];

    /**
     * In-memory request cache — evita múltiplas queries na mesma request,
     * mas garante que cada request nova leia o valor fresco do banco
     * (sem risco de cache-file stale).
     */
    protected static array $memory = [];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key] ?? $default;
        }

        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return self::$memory[$key] = $default;
        }

        $value = match ($setting->type) {
            'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };

        return self::$memory[$key] = $value;
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', string $group = 'geral'): self
    {
        $stored = match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };

        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group]
        );

        self::forgetMemory($key);

        return $setting;
    }

    public static function forgetMemory(?string $key = null): void
    {
        if ($key === null) {
            self::$memory = [];

            return;
        }
        unset(self::$memory[$key]);
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => self::forgetMemory($s->key));
        static::deleted(fn (Setting $s) => self::forgetMemory($s->key));
    }
}
