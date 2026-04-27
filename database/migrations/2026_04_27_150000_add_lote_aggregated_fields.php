<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria conceitual 2026-04-27 — campos para gestão agregada de lote.
 *
 * Permite que aves (1.500 frangos), peixes (5.000 alevinos), suínos creche
 * sejam cadastrados como LOTE sem criar 1 row por animal. Campos novos:
 *
 *   peso_medio_kg          → peso médio do lote (atualizado por pesagem amostral/biomassa)
 *   data_inicio            → quando o lote começou a operar (ex.: data alojamento aves)
 *   data_fim               → quando o lote foi encerrado (ex.: data despesca peixes)
 *   partner_id_aquisicao   → fornecedor (quando modo compra agregada)
 *   valor_aquisicao        → valor total pago no lote (modo compra)
 *   custo_unitario         → custo por unidade (valor_aquisicao / quantidade_inicial)
 *
 * Não é destrutivo. Lotes existentes ficam com NULL nos novos campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->decimal('peso_medio_kg', 10, 2)->nullable()->after('quantidade_atual');
            $table->date('data_inicio')->nullable()->after('peso_medio_kg');
            $table->date('data_fim')->nullable()->after('data_inicio');
            $table->unsignedBigInteger('partner_id_aquisicao')->nullable()->after('data_fim');
            $table->decimal('valor_aquisicao', 12, 2)->nullable()->after('partner_id_aquisicao');
            $table->decimal('custo_unitario', 10, 4)->nullable()->after('valor_aquisicao');

            $table->foreign('partner_id_aquisicao')
                ->references('id')->on('partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->dropForeign(['partner_id_aquisicao']);
            $table->dropColumn([
                'peso_medio_kg', 'data_inicio', 'data_fim',
                'partner_id_aquisicao', 'valor_aquisicao', 'custo_unitario',
            ]);
        });
    }
};
