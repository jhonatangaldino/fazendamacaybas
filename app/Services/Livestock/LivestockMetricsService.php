<?php

namespace App\Services\Livestock;

use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalEvent;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Domain\Tenancy\Scopes\BelongsToTenantScope;
use App\Domain\Tenancy\Scopes\BelongsToFarmScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * LivestockMetricsService — fonte ÚNICA de verdade para KPIs do rebanho.
 *
 * Antes desta classe cada controller calculava seus próprios totais. Em
 * produção o usuário viu Bovino · Total = 344 no Dashboard, 345 no Hub,
 * 480kg de peso médio no Dashboard vs 309kg na Listagem, 78L de leite no
 * Dashboard vs 116L no Controle Leiteiro, 1 vaca em lactação vs 53. Tudo
 * por causa de fórmulas diferentes pro mesmo conceito.
 *
 * REGRAS:
 *   1. Eloquent sempre — SoftDeletes e BelongsToTenant/BelongsToFarm
 *      global scopes filtram automaticamente.
 *   2. Caller passa $tenantId / $farmId quando precisa contornar o scope
 *      do request atual (ex.: middleware Inertia computando para tenant
 *      impersonado, ou cache global tenant-wide ignorando farm).
 *   3. Service NÃO cacheia — cada caller conhece o TTL apropriado.
 *
 * Fórmulas canônicas documentadas em qa-evidence/audit-2026-04-28/METRICS-DESIGN.md.
 */
class LivestockMetricsService
{
    /**
     * Total de cabeças ATIVAS de uma espécie.
     *
     * Fórmula:
     *   COUNT(animals WHERE species_id=$sid AND status='ativo' AND deleted_at IS NULL)
     *   + (se species.gestao='lote')
     *     SUM(animal_lots.quantidade_atual WHERE species_id=$sid AND is_active=true)
     *
     * Para gestão individual NÃO soma lotes — animals individuais já cobre.
     * Para gestão lote (Ave/Peixe) soma cabeças agregadas + Animals legados.
     */
    public function totalCabecasPorEspecie(int $speciesId, ?int $tenantId = null, ?int $farmId = null): int
    {
        $species = $this->resolveSpecies($speciesId);
        if ($species === null) {
            return 0;
        }

        $individuais = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->count();

        $agregados = 0;
        if ($species->gestao === 'lote') {
            $agregados = (int) $this->lotQuery($tenantId, $farmId)
                ->where('species_id', $speciesId)
                ->where('is_active', true)
                ->sum('quantidade_atual');
        }

        return $individuais + $agregados;
    }

    /**
     * Total geral de cabeças ATIVAS (todas as espécies).
     *
     * Substitui as 3 cópias em LivestockIndexController, DashboardController
     * e ReportController, que calculavam o mesmo número de jeitos sutilmente
     * diferentes.
     */
    public function totalCabecasTodasEspecies(?int $tenantId = null, ?int $farmId = null): int
    {
        $individuais = $this->animalQuery($tenantId, $farmId)
            ->where('status', 'ativo')
            ->count();

        $agregados = (int) $this->lotQuery($tenantId, $farmId)
            ->where('is_active', true)
            ->whereIn('species_id', $this->speciesIdsGestaoLote())
            ->sum('quantidade_atual');

        return $individuais + $agregados;
    }

    /**
     * Cabeças por espécie — Collection com total para CADA espécie ativa.
     *
     * Estrutura de cada item:
     *   ['id'=>int, 'nome'=>string, 'slug'=>string, 'gestao'=>string,
     *    'profile'=>string, 'animals_count'=>int]
     *
     * Esta é a estrutura consumida pelo middleware HandleInertiaRequests
     * para o badge de menu, e usada em Painel/Relatório como "por_especie".
     */
    public function cabecasPorEspecie(?int $tenantId = null, ?int $farmId = null): Collection
    {
        $species = AnimalSpecies::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'slug', 'gestao', 'profile']);

        $individuais = $this->animalQuery($tenantId, $farmId)
            ->select('species_id', \DB::raw('COUNT(*) as cnt'))
            ->where('status', 'ativo')
            ->whereIn('species_id', $species->pluck('id'))
            ->groupBy('species_id')
            ->pluck('cnt', 'species_id');

        $agregados = $this->lotQuery($tenantId, $farmId)
            ->select('species_id', \DB::raw('COALESCE(SUM(quantidade_atual), 0) as cnt'))
            ->where('is_active', true)
            ->whereIn('species_id', $species->where('gestao', 'lote')->pluck('id'))
            ->groupBy('species_id')
            ->pluck('cnt', 'species_id');

        return $species->map(fn ($s) => [
            'id'      => $s->id,
            'nome'    => $s->nome,
            'slug'    => $s->slug,
            'gestao'  => $s->gestao,
            'profile' => $s->profile,
            'animals_count' => $s->gestao === 'lote'
                ? (int) (($agregados[$s->id] ?? 0) + ($individuais[$s->id] ?? 0))
                : (int) ($individuais[$s->id] ?? 0),
        ]);
    }

    /**
     * Peso médio dos animais ATIVOS de uma espécie, derivado do ÚLTIMO
     * evento de pesagem por animal. Não usa `animals.peso_atual` direto:
     * esse campo costuma ficar stale, gerando divergência (480kg vs 309kg
     * em produção).
     *
     * Retorna null quando não há pesagens — UI mostra "—".
     * Aplicável apenas para gestao=individual.
     */
    public function pesoMedioPorEspecie(int $speciesId, ?int $tenantId = null, ?int $farmId = null): ?float
    {
        $species = $this->resolveSpecies($speciesId);
        if ($species === null || $species->gestao !== 'individual') {
            return null;
        }

        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return null;
        }

        // Subquery: id do último evento de pesagem por animal.
        $ultimosIds = AnimalEvent::query()
            ->whereIn('animal_id', $animalIds)
            ->where('tipo', 'pesagem')
            ->whereNotNull('peso')
            ->select(\DB::raw('MAX(id) as id'))
            ->groupBy('animal_id')
            ->pluck('id');

        if ($ultimosIds->isEmpty()) {
            return null;
        }

        $media = AnimalEvent::whereIn('id', $ultimosIds)->avg('peso');
        return $media !== null ? round((float) $media, 2) : null;
    }

    /**
     * Soma do último peso de todos os animais ativos da espécie + quantos
     * têm peso registrado. Útil para "resumo" da listagem.
     *
     * Retorna ['peso_total' => float, 'ativos_com_peso' => int].
     */
    public function pesoTotalAtivos(int $speciesId, ?int $tenantId = null, ?int $farmId = null): array
    {
        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return ['peso_total' => 0.0, 'ativos_com_peso' => 0];
        }

        $ultimosIds = AnimalEvent::query()
            ->whereIn('animal_id', $animalIds)
            ->where('tipo', 'pesagem')
            ->whereNotNull('peso')
            ->select(\DB::raw('MAX(id) as id'))
            ->groupBy('animal_id')
            ->pluck('id');

        if ($ultimosIds->isEmpty()) {
            return ['peso_total' => 0.0, 'ativos_com_peso' => 0];
        }

        $sum = (float) AnimalEvent::whereIn('id', $ultimosIds)->sum('peso');
        return [
            'peso_total'      => round($sum, 2),
            'ativos_com_peso' => $ultimosIds->count(),
        ];
    }

    /**
     * Número de animais vendidos no mês — usa data_saida (não updated_at).
     * Animal editado em abril (ex.: troca de foto) que foi vendido em janeiro
     * NÃO conta como "vendido em abril".
     */
    public function vendidosNoMes(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): int
    {
        [$ini, $fim] = $this->resolveMes($mesRef);
        return $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'vendido')
            ->whereBetween('data_saida', [$ini->toDateString(), $fim->toDateString()])
            ->count();
    }

    /**
     * Baixas (morto/abatido) no mês — usa data_saida.
     */
    public function baixasNoMes(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): int
    {
        [$ini, $fim] = $this->resolveMes($mesRef);
        return $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->whereIn('status', ['morto', 'abatido'])
            ->whereBetween('data_saida', [$ini->toDateString(), $fim->toDateString()])
            ->count();
    }

    /** Total ativos da espécie (sem janela temporal). */
    public function ativosTotal(int $speciesId, ?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->count();
    }

    /** Total vendidos histórico. */
    public function vendidosTotal(int $speciesId, ?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'vendido')
            ->count();
    }

    /** Total baixas histórico. */
    public function baixasTotal(int $speciesId, ?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->whereIn('status', ['morto', 'abatido'])
            ->count();
    }

    /**
     * Total de litros produzidos no mês.
     *
     * Inclui ambos tipos (ordenha + controle_leiteiro) — eventos legados
     * cadastrados pelo wizard antigo "controle_leiteiro" precisam contar.
     *
     * Cada evento pode ter o array JSON `ordenhas[]` (manhã/tarde/noite) OU
     * o campo único `producao_litros`. Se o array existir, soma cada item;
     * caso contrário, soma o campo único.
     *
     * Animais incluídos: TODOS com species_id correspondente, independente
     * de status. Vaca ordenhada em 02/04 e vendida em 15/04 ainda conta
     * para abril — produziu, então conta.
     */
    public function litrosNoMes(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): float
    {
        [$ini, $fim] = $this->resolveMes($mesRef);

        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return 0.0;
        }

        $eventos = $this->eventQuery($tenantId, $farmId)
            ->whereIn('tipo', ['ordenha', 'controle_leiteiro'])
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->whereNotNull('animal_id')
            ->whereIn('animal_id', $animalIds)
            ->get(['producao_litros', 'ordenhas']);

        $total = 0.0;
        foreach ($eventos as $ev) {
            if (! empty($ev->ordenhas) && is_array($ev->ordenhas)) {
                foreach ($ev->ordenhas as $o) {
                    $total += (float) ($o['litros'] ?? 0);
                }
            } elseif ($ev->producao_litros) {
                $total += (float) $ev->producao_litros;
            }
        }
        return round($total, 2);
    }

    /**
     * Vacas em lactação no mês — distinct animais com pelo menos um evento
     * (ordenha ou controle_leiteiro) no mês.
     *
     * Substitui dois números diferentes em produção:
     *  - Dashboard "Em lactação" (30 dias rolling, só ordenha) = 1
     *  - Controle Leiteiro "vacas_ordenhadas" (mês, ordenha+controle) = 53
     * Agora ambos chamam este método e mostram o MESMO número.
     */
    public function vacasEmLactacao(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): int
    {
        [$ini, $fim] = $this->resolveMes($mesRef);

        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return 0;
        }

        return $this->eventQuery($tenantId, $farmId)
            ->whereIn('tipo', ['ordenha', 'controle_leiteiro'])
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->whereIn('animal_id', $animalIds)
            ->distinct('animal_id')
            ->count('animal_id');
    }

    /**
     * Maior produtora do mês para a espécie. Retorna null se ninguém
     * produziu. Usa mesma janela e tipos de litrosNoMes.
     */
    public function topProdutoraDoMes(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): ?array
    {
        [$ini, $fim] = $this->resolveMes($mesRef);

        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return null;
        }

        $eventos = $this->eventQuery($tenantId, $farmId)
            ->whereIn('tipo', ['ordenha', 'controle_leiteiro'])
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->whereIn('animal_id', $animalIds)
            ->get(['animal_id', 'producao_litros', 'ordenhas']);

        if ($eventos->isEmpty()) return null;

        // Soma por animal
        $somaPorAnimal = [];
        foreach ($eventos as $ev) {
            $litros = 0.0;
            if (! empty($ev->ordenhas) && is_array($ev->ordenhas)) {
                foreach ($ev->ordenhas as $o) {
                    $litros += (float) ($o['litros'] ?? 0);
                }
            } elseif ($ev->producao_litros) {
                $litros = (float) $ev->producao_litros;
            }
            $somaPorAnimal[$ev->animal_id] = ($somaPorAnimal[$ev->animal_id] ?? 0) + $litros;
        }

        if (empty($somaPorAnimal)) return null;

        arsort($somaPorAnimal);
        $topId = (int) array_key_first($somaPorAnimal);
        $topLitros = (float) $somaPorAnimal[$topId];
        if ($topLitros <= 0) return null;

        $animal = $this->animalQuery($tenantId, $farmId)->find($topId, ['id', 'identificacao', 'nome']);
        if (! $animal) return null;

        return [
            'animal_id'    => $animal->id,
            'identificacao'=> $animal->identificacao,
            'nome'         => $animal->nome,
            'total_litros' => round($topLitros, 2),
        ];
    }

    /**
     * Eventos nos últimos N dias — inclui eventos individuais (com animal_id)
     * E agregados (apenas lot_id) que pertençam à espécie. Sem o "OU lot",
     * Ave/Peixe sempre mostrava 0 mesmo após registrar mortalidade em lote.
     */
    public function eventosUltimosNDias(int $speciesId, int $n = 7, ?int $tenantId = null, ?int $farmId = null): int
    {
        $deadline = now()->subDays($n)->toDateString();
        return $this->eventQuery($tenantId, $farmId)
            ->where('data', '>=', $deadline)
            ->where(function ($q) use ($speciesId) {
                $q->whereHas('animal', fn ($qq) => $qq->where('species_id', $speciesId))
                  ->orWhereHas('lot', fn ($qq) => $qq->where('species_id', $speciesId));
            })
            ->count();
    }

    /** Lotes ativos vinculados à espécie. */
    public function lotesAtivosPorEspecie(int $speciesId, ?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->lotQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Detecta se a fazenda pratica manejo leiteiro (mostra/esconde menus
     * relacionados). Critério: existe ≥1 animal categoria leite/misto OU
     * ≥1 evento ordenha/controle_leiteiro.
     *
     * Quando $speciesId é informado, restringe à espécie. Sem ele, considera
     * o tenant inteiro.
     */
    public function temManejoLeiteiro(?int $speciesId = null, ?int $tenantId = null, ?int $farmId = null): bool
    {
        $animalQuery = $this->animalQuery($tenantId, $farmId)
            ->whereIn('categoria', ['leite', 'misto']);
        if ($speciesId) $animalQuery->where('species_id', $speciesId);
        if ($animalQuery->exists()) return true;

        $eventQuery = $this->eventQuery($tenantId, $farmId)
            ->whereIn('tipo', ['ordenha', 'controle_leiteiro']);
        if ($speciesId) {
            $eventQuery->whereHas('animal', fn ($q) => $q->where('species_id', $speciesId));
        }
        return $eventQuery->exists();
    }

    /** Distribuição por sexo de animais ATIVOS da espécie. */
    public function sexoCount(int $speciesId, ?int $tenantId = null, ?int $farmId = null): array
    {
        $rows = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->select('sexo', \DB::raw('COUNT(*) as total'))
            ->groupBy('sexo')
            ->pluck('total', 'sexo')
            ->toArray();

        return [
            'M' => (int) ($rows['M'] ?? 0),
            'F' => (int) ($rows['F'] ?? 0),
        ];
    }

    /**
     * Categorias DROVET no dia de referência:
     *   vacas_secas, vacas_lactacao, novilhas, bezerras, machos.
     *
     * Critérios:
     *   - vacas_secas: F + adulta + tem evento 'secagem' SEM controle_leiteiro
     *                  posterior à secagem (ainda em descanso)
     *   - vacas_lactacao: F + adulta + sem secagem ativa + ≥1 evento
     *                     controle_leiteiro|ordenha nos últimos 60 dias
     *   - novilhas: F + idade > 12 meses + sem critério de seca/lactação
     *   - bezerras: F + idade ≤ 12 meses
     *   - machos: M (qualquer idade)
     */
    public function contarCategoriasLeiteiras(int $speciesId, Carbon $dataRef, ?int $tenantId = null, ?int $farmId = null): array
    {
        $animais = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->get(['id', 'sexo', 'data_nascimento']);

        $vacasSecas = 0;
        $vacasLact  = 0;
        $novilhas   = 0;
        $bezerras   = 0;
        $machos     = 0;

        foreach ($animais as $a) {
            if ($a->sexo === 'M') {
                $machos++;
                continue;
            }
            $idadeMeses = $a->data_nascimento ? (int) $a->data_nascimento->diffInMonths($dataRef) : null;
            if ($idadeMeses !== null && $idadeMeses <= 12) {
                $bezerras++;
                continue;
            }

            $ultimaSecagem = $this->eventQuery($tenantId, $farmId)
                ->where('animal_id', $a->id)
                ->where('tipo', 'secagem')
                ->where('data', '<=', $dataRef->toDateString())
                ->orderByDesc('data')
                ->first(['data']);

            if ($ultimaSecagem) {
                $voltou = $this->eventQuery($tenantId, $farmId)
                    ->where('animal_id', $a->id)
                    ->whereIn('tipo', ['controle_leiteiro', 'ordenha'])
                    ->where('data', '>', $ultimaSecagem->data)
                    ->where('data', '<=', $dataRef->toDateString())
                    ->exists();
                if (! $voltou) {
                    $vacasSecas++;
                    continue;
                }
            }

            $emLactacao = $this->eventQuery($tenantId, $farmId)
                ->where('animal_id', $a->id)
                ->whereIn('tipo', ['controle_leiteiro', 'ordenha'])
                ->whereBetween('data', [
                    $dataRef->copy()->subDays(60)->toDateString(),
                    $dataRef->toDateString(),
                ])
                ->exists();
            if ($emLactacao) {
                $vacasLact++;
            } else {
                $novilhas++;
            }
        }

        return [
            'vacas_secas'    => $vacasSecas,
            'vacas_lactacao' => $vacasLact,
            'novilhas'       => $novilhas,
            'bezerras'       => $bezerras,
            'machos'         => $machos,
            'total_femeas'   => $vacasSecas + $vacasLact + $novilhas + $bezerras,
            'total_geral'    => $vacasSecas + $vacasLact + $novilhas + $bezerras + $machos,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Profile-específicos: postura (aves), aquicultura
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ovos no mês — total agregado de eventos `postura_diaria`.
     *
     * Fórmula canônica:
     *   SUM(animal_events.peso) WHERE tipo='postura_diaria'
     *   AND data BETWEEN [inicioMes, fimMes]
     *   AND animal_id IN (animais ativos da espécie)
     *
     * Coluna `peso` é usada por legado para armazenar quantidade de ovos
     * em eventos de postura (mesmo schema reaproveitado).
     *
     * Bug corrigido (alta-8): AnimalController::kpisProfileEspecie tinha
     * essa fórmula inline; agora vive aqui pra ser canônica.
     */
    public function ovosNoMes(int $speciesId, ?Carbon $mesRef = null, ?int $tenantId = null, ?int $farmId = null): int
    {
        [$ini, $fim] = $this->resolveMes($mesRef);

        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->pluck('id');

        if ($animalIds->isEmpty()) {
            return 0;
        }

        return (int) $this->eventQuery($tenantId, $farmId)
            ->whereIn('animal_id', $animalIds)
            ->where('tipo', 'postura_diaria')
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->sum('peso');
    }

    /**
     * Média de ovos por dia nos últimos N dias (default 7).
     *
     * Fórmula: SUM(peso) eventos postura_diaria nos últimos N dias / N.
     * Usa N (não COUNT(distinct dias)) — média conta dias zerados também.
     */
    public function mediaOvosPorDia(int $speciesId, int $dias = 7, ?int $tenantId = null, ?int $farmId = null): float
    {
        $animalIds = $this->animalQuery($tenantId, $farmId)
            ->where('species_id', $speciesId)
            ->where('status', 'ativo')
            ->pluck('id');

        if ($animalIds->isEmpty() || $dias <= 0) {
            return 0.0;
        }

        $total = (int) $this->eventQuery($tenantId, $farmId)
            ->whereIn('animal_id', $animalIds)
            ->where('tipo', 'postura_diaria')
            ->where('data', '>=', now()->subDays($dias)->toDateString())
            ->sum('peso');

        return round($total / $dias, 1);
    }

    /**
     * Última biometria (aquicultura) — evento mais recente de
     * `biometria_amostral` para qualquer animal/lote da espécie.
     *
     * Retorna ['data' => 'd/m/Y', 'peso' => float] ou null.
     */
    public function ultimaBiometria(int $speciesId, ?int $tenantId = null, ?int $farmId = null): ?array
    {
        $ev = $this->eventQuery($tenantId, $farmId)
            ->where('tipo', 'biometria_amostral')
            ->where(function ($q) use ($speciesId) {
                $q->whereHas('animal', fn ($qq) => $qq->where('species_id', $speciesId))
                  ->orWhereHas('lot', fn ($qq) => $qq->where('species_id', $speciesId));
            })
            ->orderByDesc('data')
            ->first(['data', 'peso']);

        if (! $ev) {
            return null;
        }

        return [
            'data' => $ev->data?->format('d/m/Y'),
            'data_iso' => $ev->data?->toDateString(),
            'peso' => $ev->peso !== null ? (float) $ev->peso : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Builder de Animal com tenant/farm explícitos quando informados.
     * Quando $tenantId/$farmId não são passados, confia nos global scopes
     * BelongsToTenant/BelongsToFarm já aplicados pelos middlewares HTTP.
     *
     * Quando $tenantId é passado, removemos o global scope tenant para
     * permitir leitura cross-tenant (caso do middleware Inertia computando
     * tenant impersonado em cache global) e aplicamos o WHERE explícito.
     */
    private function animalQuery(?int $tenantId, ?int $farmId): Builder
    {
        $q = Animal::query();
        if ($tenantId !== null) {
            $q->withoutGlobalScope(BelongsToTenantScope::class)
              ->where('animals.tenant_id', $tenantId);
        }
        if ($farmId !== null) {
            $q->withoutGlobalScope(BelongsToFarmScope::class)
              ->where('animals.farm_id', $farmId);
        }
        return $q;
    }

    private function lotQuery(?int $tenantId, ?int $farmId): Builder
    {
        $q = AnimalLot::query();
        if ($tenantId !== null) {
            $q->withoutGlobalScope(BelongsToTenantScope::class)
              ->where('animal_lots.tenant_id', $tenantId);
        }
        if ($farmId !== null) {
            $q->withoutGlobalScope(BelongsToFarmScope::class)
              ->where('animal_lots.farm_id', $farmId);
        }
        return $q;
    }

    private function eventQuery(?int $tenantId, ?int $farmId): Builder
    {
        $q = AnimalEvent::query();
        if ($tenantId !== null) {
            $q->withoutGlobalScope(BelongsToTenantScope::class)
              ->where('animal_events.tenant_id', $tenantId);
        }
        if ($farmId !== null) {
            $q->withoutGlobalScope(BelongsToFarmScope::class)
              ->where('animal_events.farm_id', $farmId);
        }
        return $q;
    }

    private function resolveSpecies(int $speciesId): ?AnimalSpecies
    {
        return AnimalSpecies::withoutGlobalScopes()->find($speciesId);
    }

    private function speciesIdsGestaoLote(): array
    {
        return AnimalSpecies::withoutGlobalScopes()
            ->where('gestao', 'lote')
            ->pluck('id')
            ->all();
    }

    /**
     * Aceita Carbon (qualquer dia do mês) ou null (mês corrente).
     * Retorna [inicio, fim] como Carbon do startOfMonth/endOfMonth.
     */
    private function resolveMes(?Carbon $mesRef): array
    {
        $ref = $mesRef ? $mesRef->copy() : Carbon::now();
        return [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()];
    }
}
