<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0 — tabela de auditoria de impersonação master → tenant.
 *
 * RESPONSABILIDADE:
 *   Registrar, com rastro permanente, cada sessão em que um usuário MASTER
 *   assumiu o contexto de um TENANT para operar/diagnosticar/dar suporte.
 *
 * DESIGN:
 *   - SEM FK — esta é uma tabela de auditoria. Preserva histórico mesmo se
 *     o impersonator (master) for removido ou o tenant for excluído. O rastro
 *     de "quem fez o quê com os dados do cliente" não pode ser rompido por
 *     cascades de delete em outras tabelas.
 *   - `ended_at` NULLABLE — sessão ativa tem NULL; sessão encerrada recebe timestamp.
 *     Permite query "quem está impersonando AGORA" via `WHERE ended_at IS NULL`.
 *   - `ip_address` VARCHAR(45) — comporta IPv6.
 *   - `user_agent` VARCHAR(512) — truncado para evitar payload absurdo; 512
 *     é suficiente para identificação de browser/device.
 *
 * ÍNDICES:
 *   - (impersonator_user_id) — "quais impersonações esse master fez?"
 *   - (tenant_id)            — "quem impersonou este tenant?"
 *   - (started_at)           — ordenação temporal em listagens
 *   - (tenant_id, ended_at)  — composto; acelera "sessão ativa nesse tenant"
 *
 * USO FUTURO:
 *   - M5 quando a impersonação for implementada: INSERT ao entrar,
 *     UPDATE ended_at ao sair (ou expirar por timeout).
 *   - Tela em /master/auditoria listando histórico.
 *
 * IDEMPOTÊNCIA:
 *   up() verifica se a tabela já existe — seguro em re-deploys parciais.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('impersonation_audit')) {
            return;
        }

        Schema::create('impersonation_audit', function (Blueprint $table) {
            $table->id();

            // Atores — sem FK por design (auditoria)
            $table->unsignedBigInteger('impersonator_user_id');
            $table->unsignedBigInteger('tenant_id');

            // Janela temporal
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();

            // Contexto de rede (defensivamente nullable — ex.: CLI)
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->index('impersonator_user_id');
            $table->index('tenant_id');
            $table->index('started_at');
            $table->index(['tenant_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_audit');
    }
};
