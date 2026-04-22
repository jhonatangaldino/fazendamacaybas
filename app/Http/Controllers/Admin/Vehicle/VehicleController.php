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

    public function vehicleStore(Request $request)
    {
        $data = $this->validateVehicle($request);
        Vehicle::create($data);

        return back()->with('success', 'Veículo cadastrado.');
    }

    public function vehicleUpdate(Request $request, Vehicle $vehicle)
    {
        $data = $this->validateVehicle($request, $vehicle->id);
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

    public function maintenanceStore(Request $request)
    {
        $data = $this->validateMaintenance($request);
        $generateTx = $request->boolean('gerar_lancamento_financeiro');
        $accountId = $request->input('account_id');

        DB::transaction(function () use ($data, $generateTx, $accountId, $request) {
            $data['valor_total'] = ($data['valor_pecas'] ?? 0) + ($data['valor_servico'] ?? 0);
            $data['created_by'] = $request->user()->id;
            $order = MaintenanceOrder::create($data);

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
        });

        return back()->with('success', 'Manutenção registrada.');
    }

    public function maintenanceUpdate(Request $request, MaintenanceOrder $order)
    {
        $data = $this->validateMaintenance($request);
        $data['valor_total'] = ($data['valor_pecas'] ?? 0) + ($data['valor_servico'] ?? 0);
        $order->update($data);

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
