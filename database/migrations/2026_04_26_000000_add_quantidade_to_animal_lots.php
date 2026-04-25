<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RN4 · BLOCO 4.4 — gestão por LOTE AGREGADO (aves/peixes/abelhas).
 *
 * Antes: cadastro de animal sempre individual (tabela `animals` com 1 row por
 * cabeça/ave/peixe). Para granjas com 2.000 frangos era impraticável — UX
 * inviável.
 *
 * Agora: lote pode ser `gestao_modo='agregada'` com `quantidade_inicial` e
 * `quantidade_atual`. Aves/peixes podem ser cadastrados como UM lote com
 * 50 ou 2000 cabeças, sem criar 2000 registros em `animals`.
 *
 * Lotes existentes ficam como `gestao_modo='individual'` (default), preservando
 * comportamento atual. Aves cadastradas individualmente continuam funcionando.
 *
 * Validação de venda (no SaleWizardController, próxima iteração):
 *   se lote.gestao_modo='agregada' E venda.quantidade > lote.quantidade_atual:
 *     → 422 "Saldo insuficiente: lote tem X, tentou vender Y"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->string('gestao_modo', 20)->default('individual')->after('finalidade')
                ->comment('individual = 1 row/animal em animals; agregada = lote com quantidade');
            $table->decimal('quantidade_inicial', 10, 2)->nullable()->after('gestao_modo')
                ->comment('Apenas se gestao_modo=agregada. Cabeças no momento da criação.');
            $table->decimal('quantidade_atual', 10, 2)->nullable()->after('quantidade_inicial')
                ->comment('Apenas se gestao_modo=agregada. Saldo após mortes/vendas/baixas.');
        });
    }

    public function down(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->dropColumn(['gestao_modo', 'quantidade_inicial', 'quantidade_atual']);
        });
    }
};
