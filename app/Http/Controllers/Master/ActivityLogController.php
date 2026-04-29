<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Domain\Billing\Models\Tenant;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Master · Auditoria — visibilidade da plataforma sobre quem fez o quê.
 *
 * Lista paginada de `activity_log` com filtros essenciais:
 *  - tenant (ver atividade de UM cliente específico, ou todos)
 *  - user (causer)
 *  - tipo de evento (created, updated, deleted)
 *  - subject_type (módulo: Animal, Lot, FinancialTransaction, etc.)
 *  - período (data início/fim)
 *
 * O activity_log é populado automaticamente pelas `LogsActivity` traits
 * já espalhadas nos models do domínio (FinancialTransaction, AnimalEvent,
 * etc.). Aqui o master TÊM acesso de leitura — não escrita.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->integer('tenant_id') ?: null;
        $causerId = $request->integer('causer_id') ?: null;
        $event = $request->string('event')->toString() ?: null;
        $subjectType = $request->string('subject_type')->toString() ?: null;
        $dataInicio = $request->date('data_inicio');
        $dataFim = $request->date('data_fim');

        $query = Activity::query()
            ->with(['causer:id,name,email,tenant_id', 'subject'])
            ->orderByDesc('id');

        if ($tenantId) $query->where('tenant_id', $tenantId);
        if ($causerId) $query->where('causer_id', $causerId);
        if ($event) $query->where('event', $event);
        if ($subjectType) $query->where('subject_type', $subjectType);
        if ($dataInicio) $query->where('created_at', '>=', $dataInicio->startOfDay());
        if ($dataFim) $query->where('created_at', '<=', $dataFim->endOfDay());

        $atividades = $query->paginate(50)->withQueryString();

        // Pré-carrega tenants + farms pra evitar N+1 queries no transform
        $tenantIds = $atividades->getCollection()->pluck('tenant_id')->filter()->unique()->values();
        $farmIds = $atividades->getCollection()->pluck('farm_id')->filter()->unique()->values();
        $tenantsCache = Tenant::whereIn('id', $tenantIds)->pluck('nome', 'id');
        $farmsCache = Farm::whereIn('id', $farmIds)->pluck('nome', 'id');

        // Mapeia para payload Vue-friendly: causer name, tenant name, farm name, subject readable
        $atividades->getCollection()->transform(function (Activity $a) use ($tenantsCache, $farmsCache) {
            return [
                'id' => $a->id,
                'tenant_id' => $a->tenant_id,
                'tenant_nome' => $a->tenant_id ? ($tenantsCache[$a->tenant_id] ?? null) : null,
                'farm_id' => $a->getAttribute('farm_id'),
                'farm_nome' => $a->getAttribute('farm_id') ? ($farmsCache[$a->getAttribute('farm_id')] ?? null) : null,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'event' => $a->event,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'causer' => $a->causer ? [
                    'id' => $a->causer->id,
                    'name' => $a->causer->name,
                    'email' => $a->causer->email,
                ] : null,
                'properties' => $a->properties,
                'created_at' => $a->created_at?->format('Y-m-d H:i:s'),
                'created_at_br' => $a->created_at?->format('d/m/Y H:i'),
            ];
        });

        // Listas para filtros (limitadas para não estourar payload)
        $tenants = Tenant::orderBy('nome')->get(['id', 'nome']);
        $eventos = Activity::distinct()->whereNotNull('event')->orderBy('event')->pluck('event');
        $subjectTypes = Activity::distinct()
            ->whereNotNull('subject_type')
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn ($t) => ['value' => $t, 'label' => class_basename($t)])
            ->unique('label')
            ->values();

        // Métricas rápidas (cabeçalho)
        $hoje = now()->startOfDay();
        $totalHoje = Activity::where('created_at', '>=', $hoje)->count();
        $totalSemana = Activity::where('created_at', '>=', now()->subDays(7))->count();
        $totalMes = Activity::where('created_at', '>=', now()->subDays(30))->count();

        return Inertia::render('Master/Atividades/Index', [
            'atividades' => $atividades,
            'filtros' => [
                'tenant_id' => $tenantId,
                'causer_id' => $causerId,
                'event' => $event,
                'subject_type' => $subjectType,
                'data_inicio' => $request->input('data_inicio'),
                'data_fim' => $request->input('data_fim'),
            ],
            'tenants' => $tenants,
            'eventos' => $eventos,
            'subject_types' => $subjectTypes,
            'metricas' => [
                'hoje' => $totalHoje,
                'ultimos_7d' => $totalSemana,
                'ultimos_30d' => $totalMes,
                'total' => Activity::count(),
            ],
        ]);
    }

    public function show(Activity $atividade): Response
    {
        $atividade->load(['causer:id,name,email', 'subject']);

        $farmId = $atividade->getAttribute('farm_id');

        return Inertia::render('Master/Atividades/Show', [
            'atividade' => [
                'id' => $atividade->id,
                'tenant_id' => $atividade->tenant_id,
                'tenant_nome' => $atividade->tenant_id ? optional(Tenant::find($atividade->tenant_id))->nome : null,
                'farm_id' => $farmId,
                'farm_nome' => $farmId ? optional(Farm::find($farmId))->nome : null,
                'log_name' => $atividade->log_name,
                'description' => $atividade->description,
                'event' => $atividade->event,
                'subject_type' => $atividade->subject_type,
                'subject_type_curto' => $atividade->subject_type ? class_basename($atividade->subject_type) : null,
                'subject_id' => $atividade->subject_id,
                'causer' => $atividade->causer ? [
                    'id' => $atividade->causer->id,
                    'name' => $atividade->causer->name,
                    'email' => $atividade->causer->email,
                ] : null,
                'properties' => $atividade->properties,
                'batch_uuid' => $atividade->batch_uuid,
                'created_at' => $atividade->created_at?->format('d/m/Y H:i:s'),
            ],
        ]);
    }
}
