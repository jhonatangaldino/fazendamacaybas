<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use App\Models\Vehicle\MaintenanceOrder;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $totals = [
            'veiculos' => Vehicle::where('is_active', true)->count(),
            'manutencoes_abertas' => MaintenanceOrder::whereIn('status', ['agendada', 'em_andamento'])->count(),
            'custo_mes' => (float) MaintenanceOrder::whereYear('data_realizada', now()->year)
                ->whereMonth('data_realizada', now()->month)
                ->sum('valor_total'),
        ];

        return Inertia::render('Admin/Vehicle/Index', ['totals' => $totals]);
    }

    public function vehicles(Request $request)
    {
        $q = Vehicle::with('farm:id,nome')
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('placa', 'like', "%{$request->search}%")
                ->orWhere('modelo', 'like', "%{$request->search}%")))
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            ->when(! $request->status || $request->status === 'ativos', fn ($qq) => $qq->where('is_active', true))
            ->orderBy('nome');

        return Inertia::render('Admin/Vehicle/Vehicles/Index', [
            'vehicles' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'tipo', 'status']),
            'farms' => Farm::where('is_active', true)->get(['id', 'nome']),
        ]);
    }

    /**
     * ═════ D5 — Consolidação de Domínio · VEÍCULOS/MÁQUINAS ═════
     *
     * Mapeia os 7 valores de `vehicles.tipo` para 3 categorias conceituais
     * (veiculo / implemento / maquina_agricola) e aplica regras coerentes
     * com o uso real de cada uma no campo.
     *
     * RETROCOMPAT:
     *   - Em UPDATE, regras só disparam se `tipo`, `placa` ou `renavam`
     *     mudaram (veículos) OU `tipo`, `placa`, `renavam` (implementos).
     *   - Tipo `outros` e combinações fora das matrizes passam sem regra
     *     extra — fallback permissivo.
     */

    /** Veículos rodoviários — exigem documentação legal (placa + RENAVAM). */
    private const TIPOS_VEICULO_COM_DOCS = ['caminhao', 'pick_up', 'motocicleta'];

    /** Implementos agrícolas — rebocados, sem motor próprio, sem documentação. */
    private const TIPOS_IMPLEMENTO = ['implemento'];

    /** Máquinas autopropelidas — exigem controle de uso via hodômetro/horímetro. */
    private const TIPOS_MAQUINA_AGRICOLA = ['trator', 'colheitadeira'];

    public function vehicleStore(Request $request)
    {
        $data = $this->validateVehicle($request);

        // D5 · Coerência tipo ↔ documentação/medidor
        if ($err = $this->validateDomainCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

        Vehicle::create($data);

        return back()->with('success', 'Veículo cadastrado.');
    }

    public function vehicleUpdate(Request $request, Vehicle $vehicle)
    {
        $data = $this->validateVehicle($request, $vehicle->id);

        // D5 · Coerência também em update — respeita legado (só valida
        // campos que mudaram).
        if ($err = $this->validateDomainCoherence($data, $vehicle)) {
            return back()->withInput()->with('error', $err);
        }

        $vehicle->update($data);

        return back()->with('success', 'Veículo atualizado.');
    }

    public function vehicleDestroy(Vehicle $vehicle)
    {
        if ($vehicle->maintenances()->exists()) {
            $vehicle->update(['is_active' => false]);

            return back()->with('warning', 'Veículo tem manutenções — foi desativado.');
        }
        $vehicle->delete();

        return back()->with('success', 'Veículo excluído.');
    }

    public function vehicleToggle(Vehicle $vehicle)
    {
        $vehicle->update(['is_active' => ! $vehicle->is_active]);

        return back();
    }

    protected function validateVehicle(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'tipo' => ['required', 'in:trator,caminhao,pick_up,motocicleta,implemento,colheitadeira,outros'],
            'nome' => ['required', 'string', 'max:150'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'ano_fabricacao' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'ano_modelo' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'placa' => ['nullable', 'string', 'max:10', Rule::unique('vehicles', 'placa')->ignore($id)->whereNull('deleted_at')],
            'renavam' => ['nullable', 'string', 'max:20'],
            'chassi' => ['nullable', 'string', 'max:50'],
            'cor' => ['nullable', 'string', 'max:30'],
            'combustivel' => ['nullable', 'string', 'max:20'],
            'medidor' => ['required', 'in:km,h'],
            'medidor_atual' => ['nullable', 'numeric', 'min:0'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0'],
            'data_aquisicao' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    /**
     * D5 · Coerência entre `tipo` e documentos/medição.
     *
     * Regras:
     *   R1. Veículos rodoviários (caminhao/pick_up/motocicleta) EXIGEM
     *       placa E RENAVAM (documentação legal obrigatória no Brasil).
     *   R2. Implementos NÃO aceitam placa nem RENAVAM (não têm motor
     *       próprio, andam rebocados).
     *   R3. Máquinas agrícolas (trator/colheitadeira) exigem medidor
     *       (horímetro/hodômetro) para controle de uso. O validator base
     *       já torna `medidor` required=in:km,h; esta camada só reforça
     *       a mensagem prescritiva caso alguma forma de bypass apareça.
     *
     * `outros` e combinações fora das matrizes passam sem regra extra.
     *
     * Em UPDATE, a regra só dispara se `tipo`, `placa` OU `renavam`
     * mudaram. Veículos legados com documento incompleto continuam
     * editáveis sem forçar preenchimento retroativo — desde que o
     * usuário não mexa nos campos afetados.
     */
    protected function validateDomainCoherence(array $data, ?Vehicle $existing): ?string
    {
        $tipo = $data['tipo'] ?? null;
        if (! $tipo) {
            return null; // já validado pelo validator base
        }

        $placa = trim((string) ($data['placa'] ?? ''));
        $renavam = trim((string) ($data['renavam'] ?? ''));

        $tipoMudou = $existing === null || $tipo !== $existing->tipo;
        $placaMudou = $existing === null || $placa !== (string) ($existing->placa ?? '');
        $renavamMudou = $existing === null || $renavam !== (string) ($existing->renavam ?? '');

        // ── R1. Veículos rodoviários exigem placa + RENAVAM ────────────────
        if (in_array($tipo, self::TIPOS_VEICULO_COM_DOCS, true)) {
            $deveValidar = $existing === null || $tipoMudou || $placaMudou || $renavamMudou;

            if ($deveValidar) {
                $faltando = [];
                if ($placa === '') $faltando[] = 'placa';
                if ($renavam === '') $faltando[] = 'RENAVAM';

                if (! empty($faltando)) {
                    $nomeCat = match ($tipo) {
                        'caminhao' => 'Caminhões',
                        'pick_up' => 'Pick-ups',
                        'motocicleta' => 'Motocicletas',
                        default => 'Veículos',
                    };

                    return "{$nomeCat} exigem placa E RENAVAM para identificação legal. "
                        . 'Falta preencher: ' . implode(' e ', $faltando) . '. '
                        . 'Estes dados são obrigatórios pelo DETRAN para qualquer veículo que circula em via pública. '
                        . 'Se este registro NÃO é um veículo rodoviário, troque o tipo para "implemento" ou "trator".';
                }
            }
        }

        // ── R2. Implementos NÃO podem ter placa nem RENAVAM ────────────────
        if (in_array($tipo, self::TIPOS_IMPLEMENTO, true)) {
            $deveValidar = $existing === null || $tipoMudou || $placaMudou || $renavamMudou;

            if ($deveValidar) {
                $indevidos = [];
                if ($placa !== '') $indevidos[] = "placa '{$placa}'";
                if ($renavam !== '') $indevidos[] = "RENAVAM '{$renavam}'";

                if (! empty($indevidos)) {
                    return 'Implementos agrícolas não possuem placa ou RENAVAM — são equipamentos rebocados (ex.: arado, grade, pulverizador) sem motor próprio. '
                        . 'Remova o(s) valor(es) indevido(s): ' . implode(' e ', $indevidos) . '. '
                        . 'Se este equipamento é autopropelido (tem motor), troque o tipo para "trator", "caminhão" ou "pick-up" conforme o caso.';
                }
            }
        }

        // ── R3. Máquinas agrícolas exigem medidor de uso ───────────────────
        // O validator base já garante `medidor` required. Este bloco só
        // dispara se o campo vazar a validação base (defesa em profundidade)
        // e dá mensagem específica do domínio.
        if (in_array($tipo, self::TIPOS_MAQUINA_AGRICOLA, true)) {
            $medidor = $data['medidor'] ?? null;
            if (empty($medidor)) {
                $rotulo = $tipo === 'trator' ? 'Tratores' : 'Colheitadeiras';

                return "{$rotulo} exigem controle de uso (horas ou km). "
                    . 'Selecione o tipo de medição no campo "Medidor": '
                    . '"h" para horímetro (mais comum em equipamento agrícola) '
                    . 'ou "km" para hodômetro. '
                    . 'Este controle é essencial para planejar manutenção preventiva.';
            }
        }

        return null;
    }

    // =============== MANUTENÇÕES ===============

    public function maintenance(Request $request)
    {
        $q = MaintenanceOrder::with(['vehicle:id,nome,placa', 'partner:id,nome'])
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->vehicle_id, fn ($qq) => $qq->where('vehicle_id', $request->vehicle_id))
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->orderByDesc('data_prevista');

        return Inertia::render('Admin/Vehicle/Maintenance/Index', [
            'orders' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'vehicle_id', 'tipo']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'placa']),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'accounts' => FinancialAccount::where('is_active', true)->get(['id', 'nome']),
        ]);
    }

    /**
     * FASE 2 · F2.4 — Integração cross-módulo:
     *   Quando a order é status=concluida com valor_total>0, o
     *   MaintenanceToExpenseService gera automaticamente uma
     *   FinancialTransaction de despesa. Coexiste com o fluxo manual
     *   opcional (checkbox `gerar_lancamento_financeiro`): o service
     *   respeita `order->transaction_id` e não duplica se o fluxo
     *   manual já criou uma FT.
     */
    public function maintenanceStore(Request $request, \App\Domain\Integration\Services\MaintenanceToExpenseService $maintExpense)
    {
        $data = $this->validateMaintenance($request);
        $generateTx = $request->boolean('gerar_lancamento_financeiro');
        $accountId = $request->input('account_id');

        DB::transaction(function () use ($data, $generateTx, $accountId, $request, $maintExpense) {
            // Schema `maintenance_orders.valor_pecas` e `valor_servico` são NOT NULL
            // mas o validator aceita-os como nullable. Coerção para 0 antes do insert
            // evita SQLSTATE[23000] quando o usuário não preenche esses campos
            // (típico em manutenção feita no próprio galpão, sem peças).
            $data['valor_pecas'] = $data['valor_pecas'] ?? 0;
            $data['valor_servico'] = $data['valor_servico'] ?? 0;
            $data['valor_total'] = $data['valor_pecas'] + $data['valor_servico'];
            $data['created_by'] = $request->user()->id;
            $order = MaintenanceOrder::create($data);

            // ── Fluxo manual antigo preservado (user marcou checkbox) ──
            if ($generateTx && $accountId && $data['valor_total'] > 0) {
                $tx = FinancialTransaction::create([
                    'account_id' => $accountId,
                    'tipo' => 'despesa',
                    'descricao' => "Manutenção: {$data['descricao']}",
                    'valor' => $data['valor_total'],
                    'data_vencimento' => $data['data_realizada'] ?? $data['data_prevista'] ?? now(),
                    'status' => isset($data['data_realizada']) ? 'pago' : 'pendente',
                    'data_pagamento' => $data['data_realizada'] ?? null,
                    'partner_id' => $data['partner_id'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $order->update(['transaction_id' => $tx->id]);
            }

            // ── F2.4 · Integração automática ──
            // Service respeita transaction_id já populado (não duplica
            // se o fluxo manual acima gerou). Dispara se status=concluida
            // + valor>0 + ainda sem FT associada.
            $order->loadMissing('vehicle');
            $maintExpense->generateForOrder($order);
        });

        return back()->with('success', 'Manutenção registrada.');
    }

    public function maintenanceUpdate(Request $request, MaintenanceOrder $order, \App\Domain\Integration\Services\MaintenanceToExpenseService $maintExpense)
    {
        $data = $this->validateMaintenance($request);
        // Mesma defesa do store: schema é NOT NULL, validator aceita nullable.
        $data['valor_pecas'] = $data['valor_pecas'] ?? 0;
        $data['valor_servico'] = $data['valor_servico'] ?? 0;
        $data['valor_total'] = $data['valor_pecas'] + $data['valor_servico'];

        // Envolve update em DB::transaction para atomicidade com a integração.
        // Caso típico: manutenção muda de "em_andamento" para "concluida" em
        // update; nesse momento o F2.4 deve disparar.
        DB::transaction(function () use ($order, $data, $maintExpense) {
            $order->update($data);
            $order->loadMissing('vehicle');
            $maintExpense->generateForOrder($order);
        });

        return back()->with('success', 'Manutenção atualizada.');
    }

    public function maintenanceDestroy(MaintenanceOrder $order)
    {
        $order->delete();

        return back()->with('success', 'Manutenção excluída.');
    }

    protected function validateMaintenance(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'tipo' => ['required', 'in:preventiva,corretiva,revisao'],
            'descricao' => ['required', 'string', 'max:255'],
            'data_prevista' => ['nullable', 'date'],
            'data_realizada' => ['nullable', 'date'],
            'medidor' => ['nullable', 'numeric', 'min:0'],
            'valor_pecas' => ['nullable', 'numeric', 'min:0'],
            'valor_servico' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:agendada,em_andamento,concluida,cancelada'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
