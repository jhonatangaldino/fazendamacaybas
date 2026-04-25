<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Integration\Services\AnimalSaleToRevenueService;
use App\Domain\Tenancy\Scopes\BelongsToTenantScope;
use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * ═════ FASE 5 · CAMADA DE INTELIGÊNCIA · VENDA ADAPTATIVA ═════
 *
 * Antes: o wizard assumia "1 venda = 1 animal" e perguntava
 *   "Qual animal você quer vender?" — ignorando a realidade do
 *   produtor (lote, peso/arroba, peixe por kg, ave por unidade).
 *
 * Agora: o wizard pergunta primeiro "O QUE você quer vender?"
 *   e se adapta:
 *
 *   • Modo `individual`         → 1 animal específico
 *   • Modo `multiplos`          → vários animais (multi-select)
 *   • Modo `lote_total`         → todos os animais ativos do lote
 *   • Modo `lote_quantidade`    → N primeiros animais ativos do lote
 *                                 (peixe, ave, suíno em massa)
 *   • Modo `peso`               → bovino vendido por arroba/kg total
 *                                 (ainda animais identificados, mas
 *                                  unidade real do mercado)
 *
 * Para todos os modos a persistência é UNIFICADA — cada AnimalEvent
 * tipo=venda criado dispara F2.1 (AnimalSaleToRevenueService) e gera
 * UMA receita financeira agregada via numero_documento BATCH:<hash>
 * ou por evento. A camada de domínio NÃO foi reescrita.
 */
class SaleWizardController extends Controller
{
    /**
     * Renderiza o wizard adaptativo com TODOS os dados necessários
     * para o front decidir os modos:
     *
     *   - animais ativos (com espécie/lote/peso)
     *   - lotes com contagem de animais ativos
     *   - espécies com profile (para inferir unidade default)
     *   - parceiros compradores
     */
    public function create()
    {
        // Reference data (espécies/raças) são compartilhadas via tenant_id=1.
        // O global scope multi-tenant filtra por tenant atual em HTTP, deixando
        // species nulo. Carregamos manualmente sem escopo e injetamos no Animal.
        $speciesById = AnimalSpecies::withoutGlobalScope(BelongsToTenantScope::class)
            ->get(['id', 'nome', 'profile', 'gestao'])
            ->keyBy('id');

        $animais = Animal::with(['breed:id,nome', 'lot:id,nome'])
            ->where('status', 'ativo')
            ->orderBy('identificacao')
            ->get([
                'id', 'identificacao', 'nome', 'sexo', 'categoria',
                'species_id', 'breed_id', 'lot_id', 'peso_atual',
                'data_nascimento', 'photo_path',
            ])
            ->map(function (Animal $a) use ($speciesById) {
                $sp = $speciesById->get($a->species_id);
                return [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'sexo' => $a->sexo,
                    'categoria' => $a->categoria,
                    'peso_atual' => $a->peso_atual,
                    'data_nascimento' => $a->data_nascimento,
                    'photo_url' => $a->photoUrl(),
                    'species_id' => $a->species_id,
                    'lot_id' => $a->lot_id,
                    'species' => $sp ? [
                        'id' => $sp->id,
                        'nome' => $sp->nome,
                        'gestao' => $sp->gestao,
                        'profile' => $sp->profile,
                    ] : null,
                    'breed' => $a->breed ? ['nome' => $a->breed->nome] : null,
                    'lot' => $a->lot ? ['nome' => $a->lot->nome] : null,
                ];
            })
            ->values();

        // Lotes com contagem viva — ajuda o usuário a escolher rapidamente
        $lotes = AnimalLot::query()
            ->withCount(['animals as ativos_count' => fn ($q) => $q->where('status', 'ativo')])
            ->orderBy('nome')
            ->get(['id', 'nome', 'species_id', 'is_active'])
            ->filter(fn ($l) => $l->ativos_count > 0)
            ->map(fn ($l) => [
                'id' => $l->id,
                'nome' => $l->nome,
                'species_id' => $l->species_id,
                'ativos_count' => $l->ativos_count,
            ])
            ->values();

        $species = $speciesById
            ->sortBy('nome')
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nome' => $s->nome,
                'profile' => $s->profile,
                'gestao' => $s->gestao,
                'unidade_default' => $this->unidadeDefaultPorProfile($s->profile),
            ]);

        return Inertia::render('Admin/SaleWizard/Index', [
            'animais' => $animais,
            'lotes' => $lotes,
            'species' => $species,
            'partners' => Partner::where('is_active', true)
                ->where(function ($q) {
                    $q->where('tipo', 'cliente')->orWhere('tipo', 'ambos');
                })
                ->orderBy('nome')
                ->get(['id', 'nome', 'pessoa', 'documento', 'telefone', 'celular'])
                ->values(),
        ]);
    }

    /**
     * Endpoint UNIFICADO de venda adaptativa.
     * Aceita todos os modos e despacha para AnimalEvent (sale).
     */
    public function store(Request $request, AnimalSaleToRevenueService $sale)
    {
        $data = $request->validate([
            'modo' => ['required', 'in:individual,multiplos,lote_total,lote_quantidade,peso'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'partner_id' => ['required', 'exists:partners,id'],
            'unidade' => ['required', 'string', 'in:cabeca,kg,arroba,saca,litro,unidade'],
            'quantidade' => ['required', 'numeric', 'min:0.001'],
            'valor_unitario' => ['required', 'numeric', 'min:0.01'],
            'valor_total' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string'],

            // Resolvidos pelo modo:
            'animal_id' => ['nullable', 'integer', 'exists:animals,id'],
            'animal_ids' => ['nullable', 'array'],
            'animal_ids.*' => ['integer', 'exists:animals,id'],
            'lot_id' => ['nullable', 'integer', 'exists:animal_lots,id'],
            'peso_medio' => ['nullable', 'numeric', 'min:0'],
            // qtd_animais é DISTINTO de quantidade (que é unitária — kg/arroba/un).
            // Em lote_quantidade, qtd_animais diz quantos bichos saem do lote;
            // quantidade diz quantos kg/un foram negociados (pode ser diferente).
            'qtd_animais' => ['nullable', 'integer', 'min:1'],
        ], [
            'data.before_or_equal' => 'A data da venda não pode ser futura.',
            'unidade.in' => 'Unidade inválida. Use: cabeça, kg, arroba, saca, litro ou unidade.',
        ]);

        // ── 1. Resolver animais a serem afetados conforme o modo ──
        $animais = $this->resolverAnimais($data);

        if ($animais->isEmpty()) {
            return back()->with('error', 'Nenhum animal disponível para esta venda.');
        }

        // Espécie homogênea — proteção anti-mistura (não vender boi com peixe)
        if ($animais->pluck('species_id')->unique()->count() > 1) {
            return back()->with('error', 'Não é possível vender animais de espécies diferentes em uma única operação.');
        }

        // ── 2. Calcular valor por cabeça (rateio) ──
        $valorPorCabeca = round($data['valor_total'] / $animais->count(), 2);

        // ── 3. Construir observações com contexto da operação ──
        $linhas = [];
        $linhas[] = sprintf(
            'Venda (%s): %s %s × %s = %s',
            $this->rotuloModo($data['modo']),
            number_format((float) $data['quantidade'], 3, ',', '.'),
            $data['unidade'],
            'R$ ' . number_format((float) $data['valor_unitario'], 2, ',', '.'),
            'R$ ' . number_format((float) $data['valor_total'], 2, ',', '.'),
        );
        if (! empty($data['peso_medio'])) {
            $linhas[] = 'Peso médio por cabeça: ' . number_format((float) $data['peso_medio'], 2, ',', '.') . ' kg';
        }
        if (! empty($data['observacoes'])) {
            $linhas[] = trim($data['observacoes']);
        }
        $obs = implode("\n", $linhas);

        // ── 4. Persistir em transação (eventos + atualização animal + receita) ──
        $criados = DB::transaction(function () use ($animais, $data, $valorPorCabeca, $obs, $request, $sale) {
            $eventos = [];
            foreach ($animais as $animal) {
                $event = $animal->events()->create([
                    'tipo' => 'venda',
                    'data' => $data['data'],
                    'valor' => $valorPorCabeca,
                    'peso' => ! empty($data['peso_medio']) ? (float) $data['peso_medio'] : null,
                    'partner_id' => $data['partner_id'],
                    'lot_id' => $animal->lot_id,
                    'observacoes' => $obs,
                    'created_by' => $request->user()?->id,
                ]);
                $animal->update(['status' => 'vendido', 'data_saida' => $data['data']]);

                // F2.1 — gera receita financeira (idempotente por evento)
                $event->loadMissing('animal');
                $sale->generateForEvent($event);

                $eventos[] = $event;
            }
            return $eventos;
        });

        $contexto = [
            'modo' => $data['modo'],
            'rotulo_modo' => $this->rotuloModo($data['modo']),
            'total_animais' => $animais->count(),
            'unidade' => $data['unidade'],
            'quantidade' => (float) $data['quantidade'],
            'valor_unitario' => (float) $data['valor_unitario'],
            'valor_total' => (float) $data['valor_total'],
            'comprador' => Partner::find($data['partner_id'])?->nome,
            'data' => $data['data'],
            'eventos_criados' => count($criados),
        ];
        session()->flash('venda_contexto', $contexto);

        $msg = sprintf(
            '%d %s vendido%s — R$ %s registrado%s no financeiro.',
            $animais->count(),
            $animais->count() === 1 ? 'animal' : 'animais',
            $animais->count() === 1 ? '' : 's',
            number_format((float) $data['valor_total'], 2, ',', '.'),
            $animais->count() === 1 ? '' : 's',
        );

        return back()->with('success', $msg);
    }

    /**
     * Despacha a resolução de quais animais participam da venda
     * conforme o modo escolhido pelo usuário.
     */
    private function resolverAnimais(array $data)
    {
        switch ($data['modo']) {
            case 'individual':
                if (empty($data['animal_id'])) {
                    abort(422, 'Selecione o animal a vender.');
                }
                return Animal::where('id', $data['animal_id'])
                    ->where('status', 'ativo')
                    ->get();

            case 'multiplos':
            case 'peso':
                if (empty($data['animal_ids']) || ! is_array($data['animal_ids'])) {
                    abort(422, 'Selecione pelo menos um animal.');
                }
                return Animal::whereIn('id', $data['animal_ids'])
                    ->where('status', 'ativo')
                    ->get();

            case 'lote_total':
                if (empty($data['lot_id'])) {
                    abort(422, 'Selecione o lote.');
                }
                return Animal::where('lot_id', $data['lot_id'])
                    ->where('status', 'ativo')
                    ->get();

            case 'lote_quantidade':
                if (empty($data['lot_id'])) {
                    abort(422, 'Selecione o lote.');
                }
                // qtd_animais (discreto) tem prioridade — é o número de animais
                // que SAEM do lote. Se não vier, cai pra quantidade (que pode
                // ser unidade real como kg) por compat — mas o front sempre envia.
                $qtd = ! empty($data['qtd_animais'])
                    ? (int) $data['qtd_animais']
                    : max(1, (int) round((float) $data['quantidade']));
                $qtd = max(1, $qtd);
                return Animal::where('lot_id', $data['lot_id'])
                    ->where('status', 'ativo')
                    ->orderBy('id')
                    ->limit($qtd)
                    ->get();

            default:
                abort(422, 'Modo de venda inválido.');
        }
    }

    /**
     * Unidade comercialmente usada por perfil de espécie.
     * Usado apenas como sugestão — o usuário sempre pode trocar.
     */
    private function unidadeDefaultPorProfile(?string $profile): string
    {
        return match ($profile) {
            'ruminante_corte', 'ruminante_lan' => 'arroba',
            'ruminante_leite' => 'cabeca',
            'suino', 'pet', 'equino' => 'cabeca',
            'ave_postura', 'ave_corte' => 'unidade',
            'aquicultura_lote' => 'kg',
            'apicultura' => 'kg',
            default => 'cabeca',
        };
    }

    private function rotuloModo(string $modo): string
    {
        return match ($modo) {
            'individual' => 'Animal individual',
            'multiplos' => 'Múltiplos animais',
            'lote_total' => 'Lote inteiro',
            'lote_quantidade' => 'Quantidade do lote',
            'peso' => 'Por peso',
            default => $modo,
        };
    }
}
