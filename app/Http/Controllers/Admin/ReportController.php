<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agricultural\Harvest;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use App\Models\Vehicle\MaintenanceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Relatórios consolidados.
     *
     * BUG FIX B4.4: TODAS as queries antes usavam DB::table('...') que BYPASSA
     * os global scopes BelongsToTenant + BelongsToFarm. Resultado: relatórios
     * mostravam dados de OUTRAS fazendas/tenants, divergindo das telas
     * detalhadas (que usam Models e respeitam scopes).
     *
     * Agora todas as queries usam Eloquent Models — scopes globais filtram
     * automaticamente por tenant_id (BelongsToTenant) e farm_id (BelongsToFarm).
     */
    public function index(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : now()->endOfMonth();

        // ─── Financeiro ───
        $receitas = FinancialTransaction::query()
            ->where('tipo', 'receita')
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$from, $to])
            ->sum('valor');

        $despesas = FinancialTransaction::query()
            ->where('tipo', 'despesa')
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$from, $to])
            ->sum('valor');

        $despesasPorCategoria = FinancialTransaction::query()
            ->leftJoin('categories', 'financial_transactions.category_id', '=', 'categories.id')
            ->where('financial_transactions.tipo', 'despesa')
            ->where('financial_transactions.status', 'pago')
            ->whereBetween('data_pagamento', [$from, $to])
            ->select(DB::raw("COALESCE(categories.nome, 'Sem categoria') as categoria"), DB::raw('SUM(financial_transactions.valor) as total'))
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        // ─── Rebanho ───
        // Inclui CABEÇAS em LOTES AGREGADOS (Ave/Peixe gestao=lote) — sem isso
        // o relatório mostrava "Ave: 1" mesmo com 4500 cabeças em lotes.
        // Bug detectado pelo dono — painel mostrava 7876, modal/relatório 1956.
        $rebanhoIndividualPorEspecie = Animal::ativos()
            ->leftJoin('animal_species', 'animals.species_id', '=', 'animal_species.id')
            ->select(DB::raw("COALESCE(animal_species.nome, 'Sem espécie') as especie"), DB::raw('COUNT(*) as total'))
            ->groupBy('especie')
            ->pluck('total', 'especie');

        $rebanhoAgregadoPorEspecie = \App\Models\Livestock\AnimalLot::where('animal_lots.is_active', true)
            ->leftJoin('animal_species', 'animal_lots.species_id', '=', 'animal_species.id')
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->where('gestao', 'lote'))
            ->select(DB::raw("COALESCE(animal_species.nome, 'Sem espécie') as especie"), DB::raw('SUM(animal_lots.quantidade_atual) as total'))
            ->groupBy('especie')
            ->pluck('total', 'especie');

        $todasEspecies = collect($rebanhoIndividualPorEspecie->keys())
            ->merge($rebanhoAgregadoPorEspecie->keys())
            ->unique();
        $rebanhoPorEspecie = $todasEspecies->map(fn ($e) => (object) [
            'especie' => $e,
            'total' => (int) ($rebanhoIndividualPorEspecie[$e] ?? 0) + (int) ($rebanhoAgregadoPorEspecie[$e] ?? 0),
        ])->sortByDesc('total')->values();

        // Por sexo: lotes agregados (Ave/Peixe) não têm sexo individualmente;
        // soma dele entra como "indefinido" pra refletir realidade.
        $sexoIndividual = Animal::ativos()
            ->select('sexo', DB::raw('COUNT(*) as total'))
            ->groupBy('sexo')
            ->pluck('total', 'sexo');
        $totalAgregado = (int) \App\Models\Livestock\AnimalLot::where('is_active', true)
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->where('gestao', 'lote'))
            ->sum('quantidade_atual');
        $rebanhoPorSexo = collect($sexoIndividual);
        if ($totalAgregado > 0) {
            // Marca como "L" (lote) pra UI poder distinguir se quiser
            $rebanhoPorSexo['L'] = $totalAgregado;
        }

        // ─── Estoque crítico ───
        $estoqueCritico = StockItem::query()
            ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
            ->where('stock_items.is_active', true)
            ->select(
                'stock_items.nome',
                'stock_items.unidade',
                'stock_items.estoque_minimo',
                DB::raw("SUM(CASE WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade WHEN stock_movements.tipo='saida' THEN -stock_movements.quantidade ELSE 0 END) as saldo")
            )
            ->groupBy('stock_items.id', 'stock_items.nome', 'stock_items.unidade', 'stock_items.estoque_minimo')
            ->havingRaw('COALESCE(saldo, 0) < stock_items.estoque_minimo')
            ->limit(30)
            ->get();

        // ─── Agrícola: produtividade por talhão ───
        $produtividade = Harvest::query()
            ->join('plantings', 'harvests.planting_id', '=', 'plantings.id')
            ->join('fields', 'plantings.field_id', '=', 'fields.id')
            ->join('crops', 'plantings.crop_id', '=', 'crops.id')
            ->whereBetween('harvests.data_colheita', [$from, $to])
            ->select(
                'fields.nome as talhao',
                'crops.nome as cultura',
                DB::raw('SUM(harvests.quantidade_colhida) as colhido'),
                DB::raw('AVG(harvests.produtividade_por_ha) as produtividade_media')
            )
            ->groupBy('fields.id', 'fields.nome', 'crops.id', 'crops.nome')
            ->orderByDesc('colhido')
            ->get();

        // ─── Máquinas: custo de manutenção por veículo ───
        $manutencoesPorVeiculo = MaintenanceOrder::query()
            ->join('vehicles', 'maintenance_orders.vehicle_id', '=', 'vehicles.id')
            ->whereBetween('data_realizada', [$from, $to])
            ->select('vehicles.nome', DB::raw('SUM(maintenance_orders.valor_total) as total'), DB::raw('COUNT(*) as qtd'))
            ->groupBy('vehicles.id', 'vehicles.nome')
            ->orderByDesc('total')
            ->get();

        // ─── Tarefas ───
        $tarefasStatus = Task::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Reports/Index', [
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'financeiro' => [
                'receitas' => (float) $receitas,
                'despesas' => (float) $despesas,
                'saldo' => (float) $receitas - (float) $despesas,
                'despesas_por_categoria' => $despesasPorCategoria,
            ],
            'rebanho' => [
                'por_especie' => $rebanhoPorEspecie,
                'por_sexo' => $rebanhoPorSexo,
                // Total = individuais + agregados — bate com card "Rebanho" do painel principal
                'total' => (int) $rebanhoPorEspecie->sum('total'),
            ],
            'estoque_critico' => $estoqueCritico,
            'produtividade' => $produtividade,
            'manutencoes' => $manutencoesPorVeiculo,
            'tarefas_status' => $tarefasStatus,
        ]);
    }
}
