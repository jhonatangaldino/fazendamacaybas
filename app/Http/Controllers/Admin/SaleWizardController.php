<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Partner;
use Inertia\Inertia;

/**
 * ═════ FASE 4 · F4.1 — FLUXO GUIADO · VENDA DE ANIMAL ═════
 *
 * Wizard de 5 passos que abstrai o fluxo de venda individual de animal,
 * pensado para o usuário leigo (dono da fazenda, vaqueiro, gerente)
 * que não precisa saber que "venda é um tipo de AnimalEvent" nem
 * entender a cadeia cross-módulo Rebanho → Financeiro.
 *
 * ─ POR QUE UM WIZARD (E NÃO MAIS UM FORM) ────────────────────────
 *
 * O fluxo existente (registrar evento no detalhe do animal) assume
 * que o usuário:
 *   1. Saiba encontrar o animal antes de agir
 *   2. Saiba que "venda" está escondida em "novo evento"
 *   3. Saiba que um parceiro precisa estar cadastrado antes
 *   4. Entenda que a integração financeira é automática
 *
 * Nenhum desses passos é óbvio para quem chega na tela. O wizard
 * reorganiza a operação em verbos que fazem sentido no campo:
 *
 *   PASSO 1 → "Qual animal você vai vender?"
 *   PASSO 2 → "Para quem você vai vender?"
 *   PASSO 3 → "Por quanto?"
 *   PASSO 4 → "Revisão"
 *   PASSO 5 → "Confirmar"
 *
 * ─ ZERO REIMPLEMENTAÇÃO DE REGRAS ────────────────────────────────
 *
 * Este controller é APENAS um expositor de dados (animais elegíveis +
 * parceiros). O submit do wizard aponta para a rota existente
 * `admin.rebanho.animais.eventos.store`, que já:
 *   - valida o evento
 *   - checa D1 (allowed_events)
 *   - cria AnimalEvent
 *   - atualiza status do animal para "vendido"
 *   - dispara F2.1 (AnimalSaleToRevenueService → FT receita)
 *
 * Wizard orquestra UX, não duplica lógica.
 *
 * ─ RETROCOMPAT ───────────────────────────────────────────────────
 *
 * Fluxos antigos continuam intocados:
 *   - Venda individual via detalhe do animal (admin.rebanho.animais.show)
 *   - Venda em lote via sellBatch
 * O wizard é ADICIONAL, não substituto.
 */
class SaleWizardController extends Controller
{
    /**
     * Renderiza o wizard de 5 passos com os dados necessários.
     *
     * Pré-carrega:
     *   - animais ELEGÍVEIS (status=ativo) com metadados de exibição
     *   - parceiros ATIVOS (são os compradores potenciais)
     *
     * Atenção: "elegível" aqui significa apenas status=ativo. O passo 1
     * ainda pode filtrar por espécie/categoria na UI. O backend D1
     * (AnimalController::storeEvent) mantém a validação final de
     * coerência na hora do submit.
     */
    public function create()
    {
        return Inertia::render('Admin/SaleWizard/Index', [
            'animais' => Animal::with(['species:id,nome,gestao,profile', 'breed:id,nome', 'lot:id,nome'])
                ->where('status', 'ativo')
                ->orderBy('identificacao')
                ->get([
                    'id', 'identificacao', 'nome', 'sexo', 'categoria',
                    'species_id', 'breed_id', 'lot_id', 'peso_atual',
                    'data_nascimento', 'photo_path',
                ])
                ->map(fn (Animal $a) => [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'sexo' => $a->sexo,
                    'categoria' => $a->categoria,
                    'peso_atual' => $a->peso_atual,
                    'data_nascimento' => $a->data_nascimento,
                    'photo_url' => $a->photoUrl(),
                    'species' => $a->species ? [
                        'id' => $a->species->id,
                        'nome' => $a->species->nome,
                        'gestao' => $a->species->gestao,
                        'profile' => $a->species->profile,
                    ] : null,
                    'breed' => $a->breed ? ['nome' => $a->breed->nome] : null,
                    'lot' => $a->lot ? ['nome' => $a->lot->nome] : null,
                ])
                ->values(),

            'partners' => Partner::where('is_active', true)
                ->where(function ($q) {
                    // Compradores: clientes (PF ou PJ) ou "ambos"
                    $q->where('tipo', 'cliente')->orWhere('tipo', 'ambos');
                })
                ->orderBy('nome')
                ->get(['id', 'nome', 'pessoa', 'documento', 'telefone', 'celular'])
                ->values(),
        ]);
    }
}
