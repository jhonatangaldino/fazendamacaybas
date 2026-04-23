<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing multi-cliente V1 — unique composto (tenant_id, slug) em cms_pages e cms_menus.
 *
 * CONTEXTO
 * Na CMS.A (migration 2024_02_04_000001) adicionamos `tenant_id` a cms_pages e
 * cms_menus, mas o UNIQUE original em `slug` permaneceu. Isso impede qualquer
 * cliente novo de ter a própria página "home" (colide com o tenant 1 Macaybas)
 * ou o próprio `header-principal`/`footer-institucional`.
 *
 * Mesma semântica aplicada a `settings` em CMS.A:
 *   DROP UNIQUE (slug) → CREATE UNIQUE (tenant_id, slug)
 *
 * Isto habilita — e só depois disso — o LandingScaffoldService rodar para
 * clientes novos. Até aqui, o schema deixava apenas 1 cliente ter página "home".
 *
 * IDEMPOTENTE
 * Check via `getIndexes()` evita duplicar a constraint em re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── cms_pages ──────────────────────────────────────────────────
        if ($this->hasIndex('cms_pages', 'cms_pages_slug_unique')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->dropUnique('cms_pages_slug_unique');
            });
        }
        if (! $this->hasIndex('cms_pages', 'cms_pages_tenant_slug_unique')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->unique(['tenant_id', 'slug'], 'cms_pages_tenant_slug_unique');
            });
        }

        // ─── cms_menus ──────────────────────────────────────────────────
        if ($this->hasIndex('cms_menus', 'cms_menus_slug_unique')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->dropUnique('cms_menus_slug_unique');
            });
        }
        if (! $this->hasIndex('cms_menus', 'cms_menus_tenant_slug_unique')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->unique(['tenant_id', 'slug'], 'cms_menus_tenant_slug_unique');
            });
        }
    }

    public function down(): void
    {
        // Reverte o unique composto e reinstala o unique simples em slug.
        // ATENÇÃO: só é seguro rodar down() enquanto houver um único tenant
        // com cada slug (o caso atual — Macaybas). Com 2+ clientes tendo
        // slug=home, o CREATE UNIQUE (slug) falharia. Down defensivo:
        if ($this->hasIndex('cms_menus', 'cms_menus_tenant_slug_unique')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->dropUnique('cms_menus_tenant_slug_unique');
            });
        }
        if (! $this->hasIndex('cms_menus', 'cms_menus_slug_unique')) {
            Schema::table('cms_menus', function (Blueprint $t) {
                $t->unique('slug', 'cms_menus_slug_unique');
            });
        }

        if ($this->hasIndex('cms_pages', 'cms_pages_tenant_slug_unique')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->dropUnique('cms_pages_tenant_slug_unique');
            });
        }
        if (! $this->hasIndex('cms_pages', 'cms_pages_slug_unique')) {
            Schema::table('cms_pages', function (Blueprint $t) {
                $t->unique('slug', 'cms_pages_slug_unique');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = \DB::select(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );

        return ! empty($rows) && (int) $rows[0]->c > 0;
    }
};
