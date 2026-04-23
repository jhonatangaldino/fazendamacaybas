<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R1.1 — Infraestrutura multi-tenant e billing SaaS (tabelas dormentes).
 *
 * Cria as 4 tabelas centrais da camada SaaS. Nesta release elas permanecem vazias;
 * o tenant default (Fazenda Macaybas) é semeado na R1.3, e a cobrança ativa só entra
 * em cena na R6 (billing enforcement).
 *
 *   - plans          Catálogo de planos comerciais
 *   - tenants        Conta cliente do SaaS — 1 tenant pode ter N fazendas
 *   - subscriptions  Assinatura ativa do tenant (1 por tenant)
 *   - invoices       Faturas mensais (PIX)
 *
 * Decisões de schema:
 *   - `status` fica como `string(20)` com default (não `enum`) para permitir
 *     adicionar novos valores sem ALTER TABLE.
 *   - `subscriptions.tenant_id` é UNIQUE: 1 assinatura ativa por tenant.
 *     Histórico de mudanças de plano fica em `subscriptions.meta` (JSON).
 *   - `invoices.numero` é UNIQUE composto com tenant_id: cada tenant tem
 *     sua própria sequência (INV-202604-0001 etc.), sem colisão entre tenants.
 *   - `plan_id` em tenants/subscriptions usa RESTRICT on delete:
 *     planos não podem ser deletados enquanto houver quem os use; desativa com
 *     is_active=false. Isso protege consistência histórica do billing.
 *   - Cascade em tenant→subscription→invoice: deletar um tenant derruba seu
 *     billing inteiro (útil para remoção LGPD; dados operacionais do tenant são
 *     apagados separadamente por TenantDeletionService na R6).
 *
 * Rollback: completamente reversível. down() dropa na ordem inversa respeitando FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1/4 · plans ────────────────────────────────────────────────────
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();          // 'essencial' | 'profissional' | 'empresarial'
            $table->string('nome', 80);
            $table->decimal('preco_mensal', 10, 2)->default(0);
            $table->unsignedInteger('max_farms')->default(1);   // 0 = ilimitado
            $table->unsignedInteger('max_users')->default(5);   // 0 = ilimitado
            $table->json('features')->nullable();               // slugs das features habilitadas
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        // ─── 2/4 · tenants ──────────────────────────────────────────────────
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('slug', 80)->unique();          // identificador humano: 'macaybas'
            $table->string('documento', 20)->nullable();   // CPF 11 dígitos OU CNPJ 14 alfanumérico (Resolução CGSIM 2026+)
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('plans')
                ->restrictOnDelete();                      // protege: não delete plan com tenants ativos
            $table->string('status', 20)->default('active');  // active | trial | past_due | blocked | canceled
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('documento');
            $table->index('is_active');
        });

        // ─── 3/4 · subscriptions ────────────────────────────────────────────
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->unique()                                  // 1 subscription ativa por tenant
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();
            $table->string('status', 20)->default('active'); // trial | active | past_due | canceled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('meta')->nullable();               // histórico de upgrades/downgrades
            $table->timestamps();

            $table->index('status');
            $table->index('current_period_end');
        });

        // ─── 4/4 · invoices ─────────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();
            $table->string('numero', 20);                   // INV-202604-0001
            $table->decimal('valor', 10, 2);
            $table->string('status', 20)->default('pendente'); // pendente | paga | vencida | cancelada
            $table->date('data_emissao');
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('pix_txid', 35)->nullable();
            $table->text('pix_payload')->nullable();        // copia-e-cola PIX
            $table->longText('pix_qrcode_base64')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Numeração por tenant (dois tenants podem ter INV-202604-0001 cada)
            $table->unique(['tenant_id', 'numero']);

            $table->index(['tenant_id', 'status']);
            $table->index('data_vencimento');
            $table->index('pix_txid');
        });
    }

    public function down(): void
    {
        // Ordem inversa — as FKs exigem que as filhas caiam primeiro
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
