<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona ao billing SaaS:
 *  - tipo da fatura (mensal | unica)
 *  - referência (mês/ano) para fatura mensal
 *  - período coberto (vigência) para qualquer fatura
 *  - aparece_em → controle de visibilidade ao cliente
 *
 * Em paralelo: adiciona controle de carência ao subscription (grace_until)
 * para suportar o ciclo "vigência expirou → 10 dias de carência → bloqueio".
 *
 * Tudo compatível com schema legado (campos novos são nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // mensal | unica — define como a fatura foi gerada
            $table->string('tipo', 10)->default('mensal')->after('numero');

            // Para faturas mensais: mês de referência (1-12) + ano (ex: 2026)
            $table->unsignedTinyInteger('referencia_mes')->nullable()->after('tipo');
            $table->unsignedSmallInteger('referencia_ano')->nullable()->after('referencia_mes');

            // Vigência coberta pela fatura (qualquer tipo).
            // Mensal: periodo_inicio = 1º dia do mês de referência, periodo_fim = último dia.
            // Única: definida pelo master no wizard.
            $table->date('periodo_inicio')->nullable()->after('referencia_ano');
            $table->date('periodo_fim')->nullable()->after('periodo_inicio');

            // Data a partir da qual a fatura fica visível para o cliente.
            // Regra: 5 dias antes do fim do mês anterior ao mês de referência.
            // Master vê tudo; tenant filtra por aparece_em <= today.
            $table->date('aparece_em')->nullable()->after('periodo_fim');

            $table->index(['tenant_id', 'aparece_em']);
            $table->index(['tenant_id', 'referencia_ano', 'referencia_mes']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Quando vigência expira sem nova fatura paga, sistema aplica carência.
            // grace_until = data_fim_vigencia + 10 dias. Após isso → bloqueado.
            $table->date('grace_until')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'aparece_em']);
            $table->dropIndex(['tenant_id', 'referencia_ano', 'referencia_mes']);
            $table->dropColumn([
                'tipo', 'referencia_mes', 'referencia_ano',
                'periodo_inicio', 'periodo_fim', 'aparece_em',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('grace_until');
        });
    }
};
