<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CMS por cliente (fase inicial — apenas base de dados).
 *
 * Torna as entidades CMS e os settings-visíveis-do-site "per-cliente", sem
 * remover nada e sem quebrar o CMS atual.
 *
 * OPERAÇÕES:
 *
 *   cms_pages:
 *     - ADD COLUMN tenant_id NULL
 *     - BACKFILL tenant_id = 1 (todas as pages atuais são do cliente Macaybas)
 *     - ALTER tenant_id NOT NULL
 *     - FK (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
 *
 *   cms_menus:
 *     - mesma sequência
 *
 *   settings:
 *     - ADD COLUMN tenant_id NULL (fica nullable — chaves globais continuam NULL)
 *     - BACKFILL tenant_id = 1 APENAS para chaves site.*, tema.*, landing.*
 *     - Chaves platform.*, billing.*, e quaisquer outras não listadas → ficam NULL
 *     - DROP UNIQUE INDEX (key) e CREATE UNIQUE INDEX (tenant_id, key)
 *     - FK (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
 *
 * SEMÂNTICA do tenant_id em settings:
 *   - NULL  → valor GLOBAL (default da plataforma, usado como fallback)
 *   - INT   → valor específico do cliente, sobrescreve o global
 *
 *   A leitura com fallback é implementada em Setting::getValue():
 *     1. tenta tenant_id = app('tenant_id')
 *     2. fallback para tenant_id NULL
 *     3. fallback para default passado
 *
 * ROLLBACK (down):
 *   Reverte tudo. Remove FKs, unique composto, coluna tenant_id.
 *   Recria o unique original em settings(key).
 *   ATENÇÃO: o rollback preserva os valores existentes. Mas se houver
 *   múltiplas linhas com mesma `key` e `tenant_id` diferente após um uso
 *   futuro real (ex.: cliente 2 tem site.nome próprio), o DROP do tenant_id
 *   deixaria chaves duplicadas e o CREATE UNIQUE (key) falharia.
 *   Rollback hoje (antes de qualquer uso real) é seguro — só existe tenant 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── CMS_PAGES ───────────────────────────────────────────────
        if (! Schema::hasColumn('cms_pages', 'tenant_id')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });

            DB::table('cms_pages')->update(['tenant_id' => 1]);

            Schema::table('cms_pages', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        // ─── CMS_MENUS ───────────────────────────────────────────────
        if (! Schema::hasColumn('cms_menus', 'tenant_id')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });

            DB::table('cms_menus')->update(['tenant_id' => 1]);

            Schema::table('cms_menus', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        // ─── SETTINGS ────────────────────────────────────────────────
        if (! Schema::hasColumn('settings', 'tenant_id')) {
            Schema::table('settings', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });

            // Backfill literal conforme brief: apenas site.*, tema.*, landing.*
            DB::table('settings')
                ->where(function ($q) {
                    $q->where('key', 'like', 'site.%')
                      ->orWhere('key', 'like', 'tema.%')
                      ->orWhere('key', 'like', 'landing.%');
                })
                ->update(['tenant_id' => 1]);

            // Chaves platform.*, billing.*, contato.*, seo.*, social.* permanecem NULL
            // (global/platform-level) — fallback de leitura cuida.

            // Substitui unique(key) por unique(tenant_id, key)
            Schema::table('settings', function (Blueprint $t) {
                $t->dropUnique('settings_key_unique');
                $t->unique(['tenant_id', 'key'], 'settings_tenant_key_unique');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // ─── SETTINGS (reverso) ──────────────────────────────────────
        if (Schema::hasColumn('settings', 'tenant_id')) {
            Schema::table('settings', function (Blueprint $t) {
                $t->dropForeign(['tenant_id']);
                $t->dropUnique('settings_tenant_key_unique');
                $t->unique('key', 'settings_key_unique');
                $t->dropColumn('tenant_id');
            });
        }

        // ─── CMS_MENUS ───────────────────────────────────────────────
        if (Schema::hasColumn('cms_menus', 'tenant_id')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->dropForeign(['tenant_id']);
                $t->dropColumn('tenant_id');
            });
        }

        // ─── CMS_PAGES ───────────────────────────────────────────────
        if (Schema::hasColumn('cms_pages', 'tenant_id')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->dropForeign(['tenant_id']);
                $t->dropColumn('tenant_id');
            });
        }
    }
};
