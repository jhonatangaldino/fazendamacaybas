<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Tabela pivot user_farm_access — controla quais fazendas (do mesmo tenant)
 * cada usuário pode acessar.
 *
 * Caso de uso real (apontado pelo dono):
 *   "Posso ter um usuário 'Veterinário' que deve ter acesso a mais de 1 fazenda
 *   mas somente daquele cliente. Isso deve ser escolhido no cadastro do user."
 *
 * Modelo:
 *   - User pertence a 1 tenant (column users.tenant_id)
 *   - User pode ter acesso a 0..N farms desse tenant via pivot
 *   - 0 farms = acesso a TODAS as farms do tenant (admin do tenant). Mantém
 *     compat com "Dono" que sempre vê tudo.
 *   - 1+ farms = restrito às farms listadas (Veterinário em 2 fazendas).
 *
 * Migração de dados: usuários existentes NÃO ganham linha — interpretado como
 * "acesso a todas as farms do tenant" (comportamento atual).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_farm_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'farm_id'], 'user_farm_unique');
            $table->index('user_id');
            $table->index('farm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_farm_access');
    }
};
