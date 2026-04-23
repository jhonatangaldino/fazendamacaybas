<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Landing multi-cliente V1 — backfill dos settings de mapa do tenant 1 (Macaybas).
 *
 * MOTIVO
 * O MapResolver (entrega V1) tem como 3ª prioridade apenas `landing.map.endereco`.
 * O tenant 1 NÃO tem esse setting preenchido ainda — seu endereço está em
 * `contato.endereco` (schema legado). Sem este backfill, o bloco do mapa na
 * landing pública `/` e `/c/macaybas` ficaria oculto após o deploy desta fase.
 *
 * O QUE FAZ
 * Copia `contato.endereco` do tenant 1 (ou fallback para o global se o tenant
 * não tiver override) para `landing.map.endereco` do tenant 1, APENAS se o
 * cliente ainda não tiver preenchido `landing.map.endereco` manualmente
 * (idempotente). Também preenche `landing.map.nome_local` com `site.nome`
 * como padrão razoável — pode ser sobrescrito depois pelo master.
 *
 * ESCOPO RESPEITADO
 * Mexe SÓ nos settings landing.map.* do tenant 1. Não toca globais nem
 * qualquer outro tenant. Rodar 2x é no-op via updateOrInsert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantId = 1;
        $now = now();

        // 1) Lê o endereço atual do tenant 1 (override) ou o global (fallback).
        $endereco = DB::table('settings')
            ->where('key', 'contato.endereco')
            ->where('tenant_id', $tenantId)
            ->value('value');

        if (empty($endereco)) {
            $endereco = DB::table('settings')
                ->where('key', 'contato.endereco')
                ->whereNull('tenant_id')
                ->value('value');
        }

        if (! empty($endereco)) {
            $this->upsertOverride(
                $tenantId,
                'landing.map.endereco',
                $endereco,
                'text',
                'Endereço para o mapa',
                $now
            );
        }

        // 2) Nome do local = nome do site (fallback razoável).
        $siteNome = DB::table('settings')
            ->where('key', 'site.nome')
            ->where('tenant_id', $tenantId)
            ->value('value');

        if (empty($siteNome)) {
            $siteNome = DB::table('settings')
                ->where('key', 'site.nome')
                ->whereNull('tenant_id')
                ->value('value');
        }

        if (! empty($siteNome)) {
            $this->upsertOverride(
                $tenantId,
                'landing.map.nome_local',
                $siteNome,
                'string',
                'Nome do local no mapa',
                $now
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('tenant_id', 1)
            ->whereIn('key', ['landing.map.endereco', 'landing.map.nome_local'])
            ->delete();
    }

    /**
     * UpdateOrInsert por (tenant_id, key). Só preenche se ainda não existir
     * um override do cliente — respeita eventual edição manual.
     */
    private function upsertOverride(int $tenantId, string $key, string $value, string $type, string $label, \Carbon\Carbon $now): void
    {
        // Só insere se ainda não existir esse override do cliente
        $exists = DB::table('settings')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'tenant_id' => $tenantId,
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'group' => 'localizacao',
            'label' => $label,
            'description' => null,
            'order_column' => 999,
            'is_public' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
