<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard do Controle Leiteiro mensal.
 *
 * Reproduz o quadro do papel DROVET+ ("Controle leiteiro · Realizado uma vez ao
 * Mês"): para cada vaca em lactação no mês selecionado, mostra a produção
 * registrada (ordenhas 1ª, 2ª, 3ª…) com TOTAL e OBS, e no rodapé exibe a
 * contagem de categorias do dia do controle:
 *   - Vacas Secas
 *   - Novilhas (acima de 1 ano)
 *   - Bezerras (até 1 ano)
 *   - Touros, Garrotes e Bezerros (machos)
 *
 * Também mostra histórico mensal comparativo (últimos 12 meses) com total de
 * litros e nº de vacas ordenhadas.
 *
 * Não é um wizard de cadastro — é VISUALIZAÇÃO consolidada do que já foi
 * registrado via wizard "Controle Leiteiro" ou modal de ordenha.
 */
class ControleLeiteiroController extends Controller
{
    public function dashboard(Request $request): Response
    {
        // Mês de referência (formato YYYY-MM). Default: mês atual.
        $mesParam = $request->query('mes', now()->format('Y-m'));
        try {
            $mesRef = Carbon::createFromFormat('Y-m', $mesParam)->startOfMonth();
        } catch (\Throwable $e) {
            $mesRef = now()->startOfMonth();
        }
        $inicioMes = $mesRef->copy()->startOfMonth();
        $fimMes    = $mesRef->copy()->endOfMonth();

        // Filtro por espécie (Bovino, Búfalo, Caprino…). Sem filtro mistura
        // todas e o usuário do dashboard de Búfalo via vacas (bug detectado em QA).
        $speciesId = $request->integer('species_id') ?: null;
        $species = null;
        if ($speciesId) {
            $species = \App\Models\Livestock\AnimalSpecies::withoutGlobalScopes()->find($speciesId);
        }

        // ── Animais em lactação no mês: têm pelo menos um evento de
        // controle_leiteiro OU ordenha dentro do intervalo + species do filtro.
        $eventosLeiteQuery = AnimalEvent::whereIn('tipo', ['controle_leiteiro', 'ordenha'])
            ->whereBetween('data', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->whereNotNull('animal_id');

        if ($speciesId) {
            $animalIdsDaEspecie = Animal::where('species_id', $speciesId)->pluck('id');
            $eventosLeiteQuery->whereIn('animal_id', $animalIdsDaEspecie);
        }

        $eventosLeite = $eventosLeiteQuery
            ->orderBy('data')
            ->get(['id', 'animal_id', 'data', 'producao_litros', 'ordenhas', 'observacoes']);

        $animalIds = $eventosLeite->pluck('animal_id')->unique()->values();

        // Dados das fêmeas em lactação (ordem por identificação)
        $vacasQuery = Animal::whereIn('id', $animalIds)
            ->with(['breed:id,nome', 'lot:id,nome'])
            ->orderBy('identificacao');
        if ($speciesId) $vacasQuery->where('species_id', $speciesId);
        $vacas = $vacasQuery->get(['id', 'identificacao', 'nome', 'sexo', 'breed_id', 'lot_id', 'data_nascimento']);

        // Indexa eventos por animal para lookup rápido
        $eventosPorAnimal = $eventosLeite->groupBy('animal_id');

        // Linhas do quadro DROVET (uma por vaca; ordenhas vêm do JSON ou da agregação)
        $linhas = $vacas->map(function (Animal $vaca) use ($eventosPorAnimal) {
            $evs = $eventosPorAnimal[$vaca->id] ?? collect();

            // Para o quadro mensal: somar todas as ordenhas do mês e separar por
            // posição (1ª, 2ª, 3ª…). Cada evento pode ter array de ordenhas.
            $ordenhasPorPosicao = ['1ª' => 0, '2ª' => 0, '3ª' => 0];
            $totalLitros = 0;
            $obsList = [];
            foreach ($evs as $ev) {
                if (! empty($ev->ordenhas) && is_array($ev->ordenhas)) {
                    foreach ($ev->ordenhas as $o) {
                        $label = $o['label'] ?? '1ª';
                        $litros = (float) ($o['litros'] ?? 0);
                        $ordenhasPorPosicao[$label] = ($ordenhasPorPosicao[$label] ?? 0) + $litros;
                        $totalLitros += $litros;
                    }
                } elseif ($ev->producao_litros) {
                    // Evento legado sem array de ordenhas — soma total na 1ª
                    $ordenhasPorPosicao['1ª'] += (float) $ev->producao_litros;
                    $totalLitros += (float) $ev->producao_litros;
                }
                if (! empty($ev->observacoes)) $obsList[] = $ev->observacoes;
            }

            return [
                'animal_id'    => $vaca->id,
                'numero'       => $vaca->identificacao,
                'nome'         => $vaca->nome,
                'raca'         => $vaca->breed?->nome,
                'lote'         => $vaca->lot?->nome,
                'ordenhas'     => $ordenhasPorPosicao,
                'total_litros' => round($totalLitros, 2),
                'observacoes'  => implode(' · ', array_unique($obsList)),
                'qtd_eventos'  => $evs->count(),
            ];
        })->values();

        // ── Contagem de categorias no dia do controle (último dia do mês)
        $contagem = $this->contarCategorias($fimMes, $speciesId);

        // ── Histórico dos últimos 12 meses
        $historico = $this->historicoMensal($mesRef, $speciesId);

        // ── Totais do mês
        $totalLitrosMes  = $linhas->sum('total_litros');
        $vacasOrdenhadas = $linhas->count();
        $mediaPorVaca    = $vacasOrdenhadas > 0 ? round($totalLitrosMes / $vacasOrdenhadas, 1) : 0;

        // Maior produtora do mês
        $top = $linhas->sortByDesc('total_litros')->first();
        $topProdutora = $top && $top['total_litros'] > 0 ? $top : null;

        // Labels adaptam à espécie ("Vacas" → "Búfalas" → "Cabras")
        $labelFemea = match (true) {
            $species?->slug === 'bufalo'  => 'Búfalas',
            $species?->slug === 'caprino' => 'Cabras',
            $species?->slug === 'ovino'   => 'Ovelhas',
            default                        => 'Vacas',
        };
        $labelCriaF = match (true) {
            $species?->slug === 'bufalo'  => 'Bezerras (búfalas)',
            $species?->slug === 'caprino' => 'Cabritas',
            $species?->slug === 'ovino'   => 'Cordeiras',
            default                        => 'Bezerras',
        };

        return Inertia::render('Admin/Livestock/ControleLeiteiro/Dashboard', [
            'mes_ref'           => $mesRef->format('Y-m'),
            'mes_label'         => $this->formatarMesPtBr($mesRef),
            'mes_anterior'      => $mesRef->copy()->subMonth()->format('Y-m'),
            'mes_posterior'     => $mesRef->copy()->addMonth()->format('Y-m'),
            'mes_atual'         => now()->format('Y-m'),
            'data_controle_br'  => $fimMes->format('d/m/Y'),
            'linhas'            => $linhas,
            'contagem'          => $contagem,
            'historico'         => $historico,
            'species'           => $species ? [
                'id' => $species->id,
                'nome' => $species->nome,
                'slug' => $species->slug,
            ] : null,
            'label_femea'       => $labelFemea,    // "Vacas" / "Búfalas" / "Cabras"
            'label_cria_f'      => $labelCriaF,    // "Bezerras" / "Cabritas" / "Cordeiras"
            'totais' => [
                'total_litros_mes'  => round($totalLitrosMes, 1),
                'vacas_ordenhadas'  => $vacasOrdenhadas,
                'media_por_vaca'    => $mediaPorVaca,
                'top_produtora'     => $topProdutora,
            ],
        ]);
    }

    /**
     * Contagem de categorias no dia do controle (snapshot DROVET).
     *
     * Categorias:
     *   - vacas_secas: F + adultas + tem evento 'secagem' SEM controle_leiteiro
     *                  posterior à secagem (ainda em descanso reprodutivo)
     *   - novilhas:    F + idade > 12 meses + sem secagem ativa + não está em
     *                  lactação (sem controle_leiteiro nos últimos 60 dias)
     *   - bezerras:    F + idade ≤ 12 meses
     *   - machos:      M (qualquer idade)
     */
    private function contarCategorias(Carbon $dataRef, ?int $speciesId = null): array
    {
        $q = Animal::where('status', 'ativo')
            ->select('id', 'sexo', 'data_nascimento');
        if ($speciesId) $q->where('species_id', $speciesId);
        $animais = $q->get();

        $vacasSecas = 0;
        $novilhas   = 0;
        $bezerras   = 0;
        $machos     = 0;
        $vacasLact  = 0; // não pedido no DROVET, mas útil pro overview

        foreach ($animais as $a) {
            if ($a->sexo === 'M') {
                $machos++;
                continue;
            }
            // F daqui pra frente
            $idadeMeses = $a->data_nascimento ? (int) $a->data_nascimento->diffInMonths($dataRef) : null;
            if ($idadeMeses !== null && $idadeMeses <= 12) {
                $bezerras++;
                continue;
            }

            // Adulta (>12 meses ou sem data_nasc): seca, lactação ou novilha?
            $ultimaSecagem = AnimalEvent::where('animal_id', $a->id)
                ->where('tipo', 'secagem')
                ->where('data', '<=', $dataRef->toDateString())
                ->orderByDesc('data')
                ->first(['data']);

            if ($ultimaSecagem) {
                // Tem controle_leiteiro depois da secagem? Se sim, voltou a produzir
                $voltou = AnimalEvent::where('animal_id', $a->id)
                    ->whereIn('tipo', ['controle_leiteiro', 'ordenha'])
                    ->where('data', '>', $ultimaSecagem->data)
                    ->where('data', '<=', $dataRef->toDateString())
                    ->exists();
                if (! $voltou) {
                    $vacasSecas++;
                    continue;
                }
            }

            // Em lactação? (controle_leiteiro nos últimos 60 dias)
            $emLactacao = AnimalEvent::where('animal_id', $a->id)
                ->whereIn('tipo', ['controle_leiteiro', 'ordenha'])
                ->whereBetween('data', [$dataRef->copy()->subDays(60)->toDateString(), $dataRef->toDateString()])
                ->exists();
            if ($emLactacao) {
                $vacasLact++;
            } else {
                $novilhas++;
            }
        }

        return [
            'vacas_secas'      => $vacasSecas,
            'vacas_lactacao'   => $vacasLact,
            'novilhas'         => $novilhas,
            'bezerras'         => $bezerras,
            'machos'           => $machos,
            'total_femeas'     => $vacasSecas + $vacasLact + $novilhas + $bezerras,
            'total_geral'      => $vacasSecas + $vacasLact + $novilhas + $bezerras + $machos,
        ];
    }

    /**
     * Histórico dos últimos 12 meses (do mês de referência pra trás).
     * Para cada mês: total de litros e nº de vacas ordenhadas.
     */
    private function historicoMensal(Carbon $mesRef, ?int $speciesId = null): array
    {
        $rows = [];
        $animalIdsDaEspecie = null;
        if ($speciesId) {
            $animalIdsDaEspecie = Animal::where('species_id', $speciesId)->pluck('id');
        }
        for ($i = 11; $i >= 0; $i--) {
            $m = $mesRef->copy()->subMonths($i)->startOfMonth();
            $inicio = $m->copy()->startOfMonth()->toDateString();
            $fim    = $m->copy()->endOfMonth()->toDateString();

            $q = AnimalEvent::whereIn('tipo', ['controle_leiteiro', 'ordenha'])
                ->whereBetween('data', [$inicio, $fim])
                ->whereNotNull('animal_id');
            if ($animalIdsDaEspecie) $q->whereIn('animal_id', $animalIdsDaEspecie);
            $eventos = $q->get(['animal_id', 'producao_litros', 'ordenhas']);

            $totalLitros = 0;
            foreach ($eventos as $ev) {
                if (! empty($ev->ordenhas) && is_array($ev->ordenhas)) {
                    foreach ($ev->ordenhas as $o) {
                        $totalLitros += (float) ($o['litros'] ?? 0);
                    }
                } elseif ($ev->producao_litros) {
                    $totalLitros += (float) $ev->producao_litros;
                }
            }

            $rows[] = [
                'mes'             => $m->format('Y-m'),
                'mes_label'       => $this->formatarMesPtBrCurto($m),
                'total_litros'    => round($totalLitros, 1),
                'vacas_ordenhadas' => $eventos->pluck('animal_id')->unique()->count(),
            ];
        }
        return $rows;
    }

    private function formatarMesPtBr(Carbon $d): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        return $meses[$d->month] . ' / ' . $d->year;
    }

    private function formatarMesPtBrCurto(Carbon $d): string
    {
        $meses = ['', 'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return $meses[$d->month] . '/' . substr($d->year, -2);
    }
}
