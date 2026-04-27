<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Livestock\AnimalEvent;
use App\Models\Livestock\AnimalLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Eventos agregados por LOTE — auditoria conceitual 2026-04-27.
 *
 * Atende C2/C3/C4 da AUDITORIA-CONCEITUAL-FORMS:
 *
 *   C2 PESAGEM AMOSTRAL/BIOMASSA
 *     - Amostragem: pesa N animais, calcula peso médio, atribui ao lote
 *     - Biomassa: kg total / qtd estimada → peso médio
 *     → atualiza animal_lots.peso_medio_kg
 *
 *   C3 MORTALIDADE EM MASSA
 *     - Galpão de aves com 100 mortes em 1 dia
 *     → decrementa animal_lots.quantidade_atual
 *
 *   C4 VACINA/VERMÍFUGO/MEDICAÇÃO PARCIAL
 *     - Vacinei 30 das 50 vacas hoje (sobraram 20 pro próximo manejo)
 *     → registra evento agregado SEM alterar quantidade do lote
 *
 * Diferença de eventos individuais (em AnimalController::storeEvent):
 *   - animal_id NULL, lot_id PREENCHIDO
 *   - quantidade_animais informado (1..quantidade_atual)
 *   - Não atualiza peso/saúde de animals individuais
 */
class AnimalLotEventController extends Controller
{
    public function store(Request $request, AnimalLot $lot): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:pesagem,vacinacao,medicacao,vermifugacao,mortalidade,observacao,biometria_amostral,postura_diaria,alimentacao,qualidade_agua'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'quantidade_animais' => ['required', 'integer', 'min:1'],

            // Pesagem amostral/biomassa: peso é peso médio CALCULADO (já vem do front)
            'peso' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            // Vacina/medicação/vermífugo
            'vacina' => ['nullable', 'string', 'max:120'],
            'medicamento' => ['nullable', 'string', 'max:120'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'via_aplicacao' => ['nullable', 'string', 'max:30'],
            'responsavel' => ['nullable', 'string', 'max:120'],

            'observacoes' => ['nullable', 'string'],
        ], [
            'data.before_or_equal' => 'A data do evento não pode ser futura.',
            'quantidade_animais.min' => 'Informe pelo menos 1 animal.',
        ]);

        // Validações de domínio condicional
        if ($data['tipo'] === 'pesagem' && empty($data['peso'])) {
            return back()->with('error', 'Pesagem do lote exige o peso médio calculado.');
        }
        if ($data['tipo'] === 'vacinacao' && empty($data['vacina'])) {
            return back()->with('error', 'Vacinação exige o nome da vacina.');
        }
        if (in_array($data['tipo'], ['medicacao', 'vermifugacao'], true) && empty($data['medicamento'])) {
            return back()->with('error', 'Medicação/Vermífugo exige o nome do produto.');
        }

        // Quantidade não pode passar do efetivo atual
        if ($data['quantidade_animais'] > $lot->quantidade_atual) {
            return back()->with('error', "Você informou {$data['quantidade_animais']} animais mas o lote tem apenas {$lot->quantidade_atual}.");
        }

        DB::transaction(function () use ($lot, $data, $request) {
            AnimalEvent::create([
                'animal_id' => null,
                'lot_id' => $lot->id,
                'quantidade_animais' => $data['quantidade_animais'],
                'tipo' => $data['tipo'],
                'data' => $data['data'],
                'peso' => $data['peso'] ?? null,
                'vacina' => $data['vacina'] ?? null,
                'medicamento' => $data['medicamento'] ?? null,
                'dose' => $data['dose'] ?? null,
                'via_aplicacao' => $data['via_aplicacao'] ?? null,
                'responsavel' => $data['responsavel'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'created_by' => $request->user()?->id,
                'farm_id' => $lot->farm_id,
            ]);

            // Efeitos colaterais por tipo:
            if ($data['tipo'] === 'pesagem' && ! empty($data['peso'])) {
                // Atualiza peso médio do lote (fonte de verdade para lotes agregados)
                $lot->update(['peso_medio_kg' => $data['peso']]);
            }

            if ($data['tipo'] === 'mortalidade') {
                // Decrementa o efetivo do lote
                $novoEfetivo = max(0, $lot->quantidade_atual - $data['quantidade_animais']);
                $update = ['quantidade_atual' => $novoEfetivo];
                if ($novoEfetivo === 0) {
                    // Lote zerado pode ser encerrado automaticamente
                    $update['data_fim'] = $data['data'];
                }
                $lot->update($update);
            }
        });

        $msg = match ($data['tipo']) {
            'pesagem' => "Pesagem registrada · peso médio do lote atualizado para {$data['peso']} kg.",
            'mortalidade' => "Mortalidade registrada · {$data['quantidade_animais']} animais. Efetivo restante do lote: " . $lot->fresh()->quantidade_atual,
            'vacinacao' => "Vacinação aplicada em {$data['quantidade_animais']} animais do lote.",
            'medicacao' => "Medicação aplicada em {$data['quantidade_animais']} animais do lote.",
            'vermifugacao' => "Vermifugação aplicada em {$data['quantidade_animais']} animais do lote.",
            default => "Evento registrado em {$data['quantidade_animais']} animais do lote.",
        };

        return back()->with('success', $msg);
    }
}
