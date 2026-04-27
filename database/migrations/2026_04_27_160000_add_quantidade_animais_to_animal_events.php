<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria conceitual 2026-04-27 — eventos agregados (C2/C3/C4).
 *
 * `animal_events.lot_id` já existia (movimentação). Mas faltava:
 *
 *   quantidade_animais → quantos animais foram efetivamente afetados pelo evento
 *
 * Necessário para os 3 cenários críticos:
 *
 *   C2 Pesagem amostral — pesei 30 frangos do galpão (lote tem 1500)
 *   C3 Mortalidade massa — morreram 100 das 1500 aves
 *   C4 Vacina parcial — vacinei 30 das 50 vacas (sobraram 20)
 *
 * Quando o evento é INDIVIDUAL: animal_id preenchido, lot_id NULL, quantidade_animais NULL
 * Quando o evento é AGREGADO:  animal_id NULL,        lot_id PREENCHIDO, quantidade_animais N
 *
 * Compatibilidade total com eventos antigos (todos individuais).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            $table->unsignedInteger('quantidade_animais')->nullable()->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            $table->dropColumn('quantidade_animais');
        });
    }
};
