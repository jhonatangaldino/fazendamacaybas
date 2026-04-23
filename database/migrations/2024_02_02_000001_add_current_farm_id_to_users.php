<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R2.6 — contexto de fazenda ativo por usuário.
 *
 * Adiciona `users.current_farm_id` NULLABLE. Persiste a escolha da fazenda
 * entre sessões/logins/devices sem depender de session storage.
 *
 * Semântica:
 *   - NULL  → ainda não escolheu. O middleware EnforceFarm decide:
 *             - tenant com 1 fazenda → auto-seleciona silenciosamente
 *             - tenant com >1 fazenda → redirect para /admin/fazenda/selecionar
 *             - master global → não intervém (valor permanece NULL)
 *   - int   → escolha persistida. Middleware valida se ainda pertence ao tenant.
 *
 * FK com ON DELETE SET NULL: se a fazenda for removida, o campo volta para
 * NULL e no próximo request o middleware resolve de novo (1-fazenda auto
 * ou seletor).
 *
 * Aditiva e idempotente: não altera dado existente, não exige backfill,
 * não bloqueia nenhum fluxo ao rodar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'current_farm_id')) {
            return; // idempotente em re-deploys
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_farm_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('farms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'current_farm_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // dropForeign precisa do nome exato do índice FK gerado pelo Laravel
            $table->dropForeign(['current_farm_id']);
            $table->dropColumn('current_farm_id');
        });
    }
};
