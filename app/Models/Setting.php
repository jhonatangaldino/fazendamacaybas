<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'tenant_id',
        'group', 'key', 'value', 'type', 'label', 'description', 'order_column', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'order_column' => 'integer',
    ];

    /**
     * In-memory request cache — keyed por `{tenantId ?? 'null'}:{key}` para
     * evitar colisão entre contexto de cliente e global.
     */
    protected static array $memory = [];

    /**
     * Leitura com fallback.
     *
     * Ordem de resolução:
     *   1. Se app('tenant_id') está bindado: busca linha com tenant_id = X
     *   2. Se não encontrou: busca linha global (tenant_id IS NULL)
     *   3. Se não encontrou: retorna $default
     *
     * Essa lógica permite que chaves platform.* fiquem só em tenant_id NULL
     * (lidas igualmente de qualquer contexto) e chaves site.*, tema.*,
     * landing.* tenham valor por cliente — com fallback ao global se o
     * cliente não tem override cadastrado.
     *
     * NÃO exige passar tenant_id: resolve via container automaticamente.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $tenantId = app()->bound('tenant_id') ? (int) app('tenant_id') : null;
        $memoryKey = ($tenantId ?? 'null') . ':' . $key;

        // Camada 1 · Request memory (micro-cache; evita múltiplas chamadas da
        // mesma key no mesmo request — ex.: 4 settings compartilhados em share()).
        if (array_key_exists($memoryKey, self::$memory)) {
            return self::$memory[$memoryKey] ?? $default;
        }

        // Camada 2 · Laravel Cache (arquivo — 1h TTL). Settings mudam raramente
        // (logo, cor_primaria, favicon), não vale 1 query MySQL por request só
        // pra ler uma string. Invalida automaticamente em saved/deleted via booted().
        $cacheKey = 'setting:' . $memoryKey;
        $cached = Cache::remember($cacheKey, now()->addHour(), function () use ($tenantId, $key) {
            $setting = null;
            if ($tenantId !== null) {
                $setting = static::where('key', $key)
                    ->where('tenant_id', $tenantId)
                    ->first();
            }
            if (! $setting) {
                $setting = static::where('key', $key)
                    ->whereNull('tenant_id')
                    ->first();
            }
            if (! $setting) {
                return ['found' => false, 'value' => null, 'type' => null];
            }
            return ['found' => true, 'value' => $setting->value, 'type' => $setting->type];
        });

        if (! $cached['found']) {
            return self::$memory[$memoryKey] = $default;
        }

        $value = match ($cached['type']) {
            'bool' => filter_var($cached['value'], FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $cached['value'],
            'json' => json_decode($cached['value'], true),
            default => $cached['value'],
        };

        return self::$memory[$memoryKey] = $value;
    }

    /**
     * Grava valor. Preserva o comportamento original (global por default)
     * com a adição opcional de tenant_id explícito.
     *
     * Assinatura estendida sem quebrar compatibilidade: chamadas existentes
     * continuam funcionando sem tenant_id (gravam linha global).
     */
    public static function setValue(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'geral',
        ?int $tenantId = null,
    ): self {
        $stored = match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };

        $setting = static::updateOrCreate(
            ['key' => $key, 'tenant_id' => $tenantId],
            ['value' => $stored, 'type' => $type, 'group' => $group]
        );

        self::forgetMemory($key, $tenantId);

        return $setting;
    }

    /**
     * Limpeza de cache — aceita tenantId opcional para precisão.
     * Sem argumentos: limpa tudo.
     */
    public static function forgetMemory(?string $key = null, ?int $tenantId = null): void
    {
        if ($key === null) {
            self::$memory = [];
            // NOTA: não limpamos o Cache facade global aqui porque a chamada
            // sem args é rara (testes). Em runtime, saved()/deleted() chamam
            // com key específico — ali SIM limpa ambos.
            return;
        }

        $tenantKey = ($tenantId ?? 'null') . ':' . $key;

        // Limpa request memory
        if ($tenantId !== null) {
            unset(self::$memory[$tenantKey]);
        } else {
            // Limpa ambas as entradas possíveis (cliente X e global)
            foreach (array_keys(self::$memory) as $cacheKey) {
                if (str_ends_with($cacheKey, ':' . $key)) {
                    unset(self::$memory[$cacheKey]);
                }
            }
        }

        // Limpa Laravel Cache (file-backed, cross-request)
        Cache::forget('setting:' . $tenantKey);
        // Se limparmos tenant-específico, também invalida o fallback global
        // (pode ter mudado). Custo baixo: 1 forget a mais.
        if ($tenantId === null) {
            foreach (array_keys(self::$memory) as $cacheKey) {
                if (str_ends_with($cacheKey, ':' . $key)) {
                    Cache::forget('setting:' . $cacheKey);
                }
            }
        }
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => self::forgetMemory($s->key, $s->tenant_id));
        static::deleted(fn (Setting $s) => self::forgetMemory($s->key, $s->tenant_id));
    }
}
