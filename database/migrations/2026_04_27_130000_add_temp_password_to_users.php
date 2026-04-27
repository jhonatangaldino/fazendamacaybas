<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Senha temporária com expiração — fluxo "convite por email".
 *
 * Ao cadastrar um user, o admin/master NÃO escolhe senha.
 * Sistema gera senha aleatória de 8 caracteres alfanuméricos sem ambíguos
 * (sem 0/O, 1/l/I), envia por email noreply@ e armazena em claro
 * (criptografado at-rest via cast 'encrypted') para que admin/master
 * veja em tela ATÉ o usuário trocar a senha no primeiro login.
 *
 *   temp_password_plaintext: senha temporária em claro (encrypted cast)
 *                            apagada quando user troca a senha
 *   password_expires_at:     timestamp de expiração (default = +2h)
 *                            quando expira, sistema regenera + reenvia email
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TEXT em vez de varchar porque o cast 'encrypted' do Laravel
            // expande o tamanho do payload (Base64 + ciphertext + IV + tag).
            $table->text('temp_password_plaintext')->nullable()->after('password');
            $table->timestamp('password_expires_at')->nullable()->after('temp_password_plaintext');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temp_password_plaintext', 'password_expires_at']);
        });
    }
};
