<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela manual_envios — auditoria de distribuição de manuais.
 *
 * Cada vez que o Master envia um manual pra um cliente (via /master/manuais),
 * grava aqui um registro com token único. Quando o destinatário clica no link
 * do e-mail, o token é validado e gravamos opened_at + IP do clique.
 *
 * Permite ao Master ver:
 *   - Quem recebeu (tenant + user)
 *   - Quando foi enviado
 *   - Se já abriu (opened_at) e quando
 *   - Quantas vezes abriu (open_count)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_envios', function (Blueprint $t) {
            $t->id();
            $t->string('token', 64)->unique();
            $t->string('manual_slug', 100);

            // Quem enviou (master)
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            // Pra quem foi (cliente + dono específico)
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $t->string('recipient_email', 191); // snapshot caso user mude o email

            // Modo de envio
            $t->enum('modo', ['anexo', 'link'])->default('link');
            $t->unsignedInteger('tamanho_kb')->nullable();

            // Mensagem personalizada do master (snapshot)
            $t->text('mensagem')->nullable();

            // Auditoria de abertura
            $t->timestamp('opened_at')->nullable();
            $t->unsignedInteger('open_count')->default(0);
            $t->ipAddress('first_open_ip')->nullable();
            $t->ipAddress('last_open_ip')->nullable();
            $t->string('last_open_user_agent', 500)->nullable();

            $t->timestamps();

            $t->index(['tenant_id', 'recipient_id']);
            $t->index('manual_slug');
            $t->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_envios');
    }
};
