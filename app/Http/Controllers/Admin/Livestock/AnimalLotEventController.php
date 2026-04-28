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
        // Lote zerado/inativo não aceita mais eventos — só observação histórica.
        // Caso contrário usuário poderia "fazer postura" em lote já encerrado.
        if (! $lot->is_active) {
            return back()->with('error', 'Lote inativo não aceita novos eventos. Reative o lote primeiro.');
        }
        if ((int) $lot->quantidade_atual <= 0 && $request->input('tipo') !== 'observacao') {
            return back()->with('error', 'Lote com efetivo zero (todas baixas registradas) não aceita mais eventos de manejo. Apenas observações são permitidas.');
        }

        $data = $request->validate([
            'tipo' => ['required', 'in:pesagem,vacinacao,medicacao,vermifugacao,mortalidade,observacao,biometria_amostral,postura_diaria,alimentacao,qualidade_agua,movimentacao'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            // quantidade_animais: pra eventos que se aplicam ao LOTE INTEIRO
            // (postura, biometria, qualidade água, alimentação) é opcional —
            // default = quantidade_atual do lote.
            'quantidade_animais' => ['nullable', 'integer', 'min:1'],
            'quantidade_baixa' => ['nullable', 'integer', 'min:1'], // alias usado em mortalidade

            // Pesagem amostral/biomassa: peso é peso médio CALCULADO
            'peso' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            // Vacina/medicação/vermífugo
            'vacina' => ['nullable', 'string', 'max:120'],
            'medicamento' => ['nullable', 'string', 'max:120'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'via_aplicacao' => ['nullable', 'string', 'max:30'],
            'responsavel' => ['nullable', 'string', 'max:120'],

            // Eventos agregados específicos
            'quantidade_ovos' => ['nullable', 'integer', 'min:0'],
            'peso_medio_amostra' => ['nullable', 'numeric', 'min:0'],
            'quantidade_amostra' => ['nullable', 'integer', 'min:1'],
            'kg_racao' => ['nullable', 'numeric', 'min:0'],
            'ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'temperatura_agua' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'oxigenio_dissolvido' => ['nullable', 'numeric', 'min:0'],

            // Movimentação — destino físico (pasto/tanque/baia)
            'location_destino_id' => ['nullable', 'exists:animal_locations,id'],
            'lot_destino_id' => ['nullable', 'exists:animal_lots,id'],

            'observacoes' => ['nullable', 'string'],
        ], [
            'data.before_or_equal' => 'A data do evento não pode ser futura.',
        ]);

        // Default qtde para eventos que afetam lote inteiro
        $qtdeAfetada = $data['quantidade_animais'] ?? null;
        if ($data['tipo'] === 'mortalidade') {
            $qtdeAfetada = $data['quantidade_baixa'] ?? $qtdeAfetada;
            if (empty($qtdeAfetada)) {
                return back()->with('error', 'Mortalidade exige a quantidade de baixas.');
            }
        }
        // Eventos de manejo do lote inteiro (não exigem qtde do user).
        // Inclui movimentacao — usuário pode mover lote inteiro pra outro pasto/baia
        // sem precisar informar qtde manualmente. Se user informar parcial, respeita.
        if (in_array($data['tipo'], ['postura_diaria', 'biometria_amostral', 'qualidade_agua', 'alimentacao', 'movimentacao'], true)) {
            $qtdeAfetada = $qtdeAfetada ?? $lot->quantidade_atual;
        }
        // Vacinação/medicação/vermífugo em LOTE: aplicação ao lote inteiro
        // por padrão (uma decisão pragmática — vacina aftosa típica é em todos).
        if (in_array($data['tipo'], ['vacinacao', 'medicacao', 'vermifugacao'], true)
            && empty($qtdeAfetada)) {
            $qtdeAfetada = $lot->quantidade_atual;
        }
        if (empty($qtdeAfetada)) {
            return back()->with('error', 'Informe quantos animais foram afetados pelo evento.');
        }

        // Validações de domínio condicional
        if ($data['tipo'] === 'pesagem' && empty($data['peso']) && empty($data['peso_medio_amostra'])) {
            return back()->with('error', 'Pesagem do lote exige o peso médio.');
        }
        if ($data['tipo'] === 'biometria_amostral' && empty($data['peso_medio_amostra'])) {
            return back()->with('error', 'Biometria amostral exige o peso médio da amostra.');
        }
        if ($data['tipo'] === 'postura_diaria' && empty($data['quantidade_ovos'])) {
            return back()->with('error', 'Postura diária exige a quantidade de ovos.');
        }
        if ($data['tipo'] === 'alimentacao' && empty($data['kg_racao'])) {
            return back()->with('error', 'Alimentação exige a quantidade de ração (kg).');
        }
        if ($data['tipo'] === 'qualidade_agua' && (! isset($data['ph']) || $data['ph'] === '')) {
            return back()->with('error', 'Qualidade da água exige pelo menos o pH.');
        }
        if ($data['tipo'] === 'vacinacao' && empty($data['vacina'])) {
            return back()->with('error', 'Vacinação exige o nome da vacina.');
        }
        if (in_array($data['tipo'], ['medicacao', 'vermifugacao'], true) && empty($data['medicamento'])) {
            return back()->with('error', 'Medicação/Vermífugo exige o nome do produto.');
        }

        // Quantidade não pode passar do efetivo atual
        if ($qtdeAfetada > $lot->quantidade_atual) {
            return back()->with('error', "Você informou {$qtdeAfetada} animais mas o lote tem apenas {$lot->quantidade_atual}.");
        }

        DB::transaction(function () use ($lot, $data, $request, $qtdeAfetada) {
            AnimalEvent::create([
                'animal_id' => null,
                'lot_id' => $lot->id,
                'quantidade_animais' => $qtdeAfetada,
                'tipo' => $data['tipo'],
                'data' => $data['data'],
                'peso' => $data['peso'] ?? $data['peso_medio_amostra'] ?? null,
                'vacina' => $data['vacina'] ?? null,
                'medicamento' => $data['medicamento'] ?? null,
                'dose' => $data['dose'] ?? null,
                'via_aplicacao' => $data['via_aplicacao'] ?? null,
                'responsavel' => $data['responsavel'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'quantidade_ovos' => $data['quantidade_ovos'] ?? null,
                'peso_medio_amostra' => $data['peso_medio_amostra'] ?? null,
                'quantidade_amostra' => $data['quantidade_amostra'] ?? null,
                'kg_racao' => $data['kg_racao'] ?? null,
                'ph' => $data['ph'] ?? null,
                'temperatura_agua' => $data['temperatura_agua'] ?? null,
                'oxigenio_dissolvido' => $data['oxigenio_dissolvido'] ?? null,
                'location_destino_id' => $data['location_destino_id'] ?? null,
                'lot_destino_id' => $data['lot_destino_id'] ?? null,
                'created_by' => $request->user()?->id,
                'farm_id' => $lot->farm_id,
            ]);

            // Efeitos colaterais por tipo:
            if (in_array($data['tipo'], ['pesagem', 'biometria_amostral'], true)) {
                $pesoNovo = $data['peso'] ?? $data['peso_medio_amostra'] ?? null;
                if ($pesoNovo) {
                    $lot->update(['peso_medio_kg' => $pesoNovo]);
                }
            }

            if ($data['tipo'] === 'mortalidade') {
                $novoEfetivo = max(0, $lot->quantidade_atual - $qtdeAfetada);
                $update = ['quantidade_atual' => $novoEfetivo];
                if ($novoEfetivo === 0) {
                    $update['data_fim'] = $data['data'];
                }
                $lot->update($update);
            }

            // Movimentação de lote agregado:
            //   1) location_destino_id → muda o local físico do lote (pasto/tanque/baia).
            //      Não mexe em quantidade — só uma mudança de endereço.
            //   2) lot_destino_id → TRANSFERE cabeças do lote origem pro destino:
            //      origem.quantidade_atual -= qtdeAfetada
            //      destino.quantidade_atual += qtdeAfetada
            //      Se a origem zerar, marca data_fim (mas não desativa, idem mortalidade).
            //
            // Bug detectado pelo dono: antes só (1) era implementado. (2) gravava
            // o evento com lot_destino_id mas as quantidades dos lotes não mudavam
            // — usuário via "movimentação registrada" mas os contadores ficavam
            // iguais. Agora a transferência é efetiva.
            if ($data['tipo'] === 'movimentacao') {
                $update = [];

                if (! empty($data['location_destino_id'])) {
                    $update['location_id'] = $data['location_destino_id'];
                }

                if (! empty($data['lot_destino_id']) && $data['lot_destino_id'] != $lot->id) {
                    // Decrementa origem
                    $novoEfetivo = max(0, $lot->quantidade_atual - $qtdeAfetada);
                    $update['quantidade_atual'] = $novoEfetivo;
                    if ($novoEfetivo === 0) {
                        $update['data_fim'] = $data['data'];
                    }

                    // Incrementa destino. AnimalLot tem BelongsToTenant scope —
                    // find() já filtra pelo tenant atual (segurança multi-tenant).
                    $destino = AnimalLot::find($data['lot_destino_id']);
                    if ($destino && $destino->is_active) {
                        $destino->update([
                            'quantidade_atual' => $destino->quantidade_atual + $qtdeAfetada,
                        ]);
                    }
                }

                if (! empty($update)) {
                    $lot->update($update);
                }
            }
        });

        $msg = match ($data['tipo']) {
            'pesagem' => "Pesagem registrada · peso médio do lote atualizado.",
            'biometria_amostral' => "Biometria registrada · peso médio: {$data['peso_medio_amostra']} kg",
            'postura_diaria' => "Postura registrada · {$data['quantidade_ovos']} ovos coletados.",
            'mortalidade' => "Mortalidade registrada · {$qtdeAfetada} animais. Efetivo restante: " . $lot->fresh()->quantidade_atual,
            'alimentacao' => "Alimentação registrada · {$data['kg_racao']} kg de ração.",
            'qualidade_agua' => "Qualidade da água registrada · pH {$data['ph']}.",
            'vacinacao' => "Vacinação aplicada em {$qtdeAfetada} animais do lote.",
            'medicacao' => "Medicação aplicada em {$qtdeAfetada} animais do lote.",
            'vermifugacao' => "Vermifugação aplicada em {$qtdeAfetada} animais do lote.",
            'movimentacao' => $this->buildMovimentacaoMsg($lot, $data, $qtdeAfetada),
            default => "Evento registrado em {$qtdeAfetada} animais do lote.",
        };

        return back()->with('success', $msg);
    }

    /**
     * Mensagem de sucesso da movimentação descreve concretamente o que mudou:
     *   • só local: "Lote movido para [pasto X]"
     *   • só transferência de cabeças: "50 cabeças transferidas para [lote Y]"
     *   • ambos: combina os dois em uma frase só
     */
    private function buildMovimentacaoMsg(AnimalLot $lot, array $data, int $qtdeAfetada): string
    {
        $partes = [];
        $fresh = $lot->fresh();

        if (! empty($data['lot_destino_id'])) {
            $destino = AnimalLot::find($data['lot_destino_id']);
            $nomeDestino = $destino?->nome ?? 'lote destino';
            $partes[] = "{$qtdeAfetada} cabeças transferidas para \"{$nomeDestino}\"";
        }

        if (! empty($data['location_destino_id'])) {
            $local = \App\Models\Livestock\AnimalLocation::find($data['location_destino_id']);
            $nomeLocal = $local?->nome ?? 'novo local';
            $partes[] = "lote movido para \"{$nomeLocal}\"";
        }

        if (empty($partes)) {
            return "Movimentação registrada.";
        }

        $resumo = implode(' · ', $partes);
        return "Movimentação registrada · {$resumo}. Efetivo restante: {$fresh->quantidade_atual} cabeças.";
    }
}
