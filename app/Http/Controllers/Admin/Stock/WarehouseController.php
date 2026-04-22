<?php

namespace App\Http\Controllers\Admin\Stock;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Stock\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Stock/Warehouses/Index', [
            'warehouses' => Warehouse::with('farm:id,nome')
                ->when($request->search, fn ($q) => $q->where('nome', 'like', "%{$request->search}%"))
                ->orderBy('nome')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('search'),
            'farms' => Farm::where('is_active', true)->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateWarehouse($request);
        Warehouse::create($data);

        return back()->with('success', 'Armazém criado.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $this->validateWarehouse($request);
        $warehouse->update($data);

        return back()->with('success', 'Armazém atualizado.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->movements()->exists() ?? false) {
            return back()->with('error', 'Armazém possui movimentações — não é possível excluir.');
        }

        $warehouse->delete();

        return back()->with('success', 'Armazém excluído.');
    }

    public function toggle(Warehouse $warehouse)
    {
        $warehouse->update(['is_active' => ! $warehouse->is_active]);

        return back()->with('success', $warehouse->is_active ? 'Armazém ativado.' : 'Armazém desativado.');
    }

    protected function validateWarehouse(Request $request): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'nome' => ['required', 'string', 'max:100'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'responsavel' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }
}
