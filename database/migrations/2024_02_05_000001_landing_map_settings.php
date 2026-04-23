<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Landing multi-cliente V1 — settings de mapa (globais, vazios).
 *
 * Registra os 5 settings novos `landing.map.*` como GLOBAIS (tenant_id = NULL)
 * com valor vazio. Funcionam como:
 *
 *   - Schema para a UI (Master/Clientes/Cms/Settings.vue lista tudo que começa
 *     com `landing.*` automaticamente, via CLIENT_PREFIXES do SettingsController);
 *   - Fallback do `Setting::getValue()` (cliente sem override → lê global vazio
 *     → `MapResolver` retorna null → bloco do mapa oculto).
 *
 * Quando um cliente preenche no CMS, vira override (tenant_id = cliente.id) via
 * `updateOrCreate(['key', 'tenant_id'])` que já existe no SettingsController.
 *
 * Idempotente: `updateOrInsert` por `(tenant_id = NULL, key)` — respeita o
 * unique composto criado em R1.2/CMS.A.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'key' => 'landing.map.nome_local',
                'type' => 'string',
                'label' => 'Nome do local no mapa',
                'description' => 'Ex.: "Fazenda Macaybas". Usado como legenda e no título do iframe.',
            ],
            [
                'key' => 'landing.map.endereco',
                'type' => 'text',
                'label' => 'Endereço para o mapa',
                'description' => 'Endereço textual (ex.: "Estrada Municipal, km 5, Itabirito/MG"). Usado quando latitude/longitude e embed estão vazios.',
            ],
            [
                'key' => 'landing.map.latitude',
                'type' => 'string',
                'label' => 'Latitude',
                'description' => 'Coordenada decimal (ex.: -20.2567). Usada em conjunto com longitude quando o embed manual não está preenchido.',
            ],
            [
                'key' => 'landing.map.longitude',
                'type' => 'string',
                'label' => 'Longitude',
                'description' => 'Coordenada decimal (ex.: -43.8042). Usada em conjunto com latitude quando o embed manual não está preenchido.',
            ],
            [
                'key' => 'landing.map.google_embed',
                'type' => 'text',
                'label' => 'Embed manual do Google Maps',
                'description' => 'Cole aqui o código do iframe (ou só o link) gerado pelo "Compartilhar → Incorporar um mapa" no Google Maps. Quando preenchido, tem prioridade sobre latitude/longitude e endereço.',
            ],
        ];

        foreach ($rows as $i => $r) {
            DB::table('settings')->updateOrInsert(
                // Chave de lookup: respeita o unique composto (tenant_id, key)
                ['tenant_id' => null, 'key' => $r['key']],
                [
                    'value' => null,
                    'type' => $r['type'],
                    'group' => 'localizacao',
                    'label' => $r['label'],
                    'description' => $r['description'],
                    'order_column' => 100 + $i,
                    'is_public' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereNull('tenant_id')
            ->whereIn('key', [
                'landing.map.nome_local',
                'landing.map.endereco',
                'landing.map.latitude',
                'landing.map.longitude',
                'landing.map.google_embed',
            ])
            ->delete();
    }
};
