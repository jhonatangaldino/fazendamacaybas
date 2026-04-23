<?php

namespace App\Http\Controllers\Master\Clientes\Cms;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * MenuController do CMS por cliente.
 *
 * Menus pertencem a um cliente (tenant_id NOT NULL). MenuItems herdam o
 * cliente via FK para menu. Master edita os menus (header/rodapé) de um
 * cliente específico.
 */
class MenuController extends Controller
{
    private function withClientContext(Tenant $cliente, Closure $fn): mixed
    {
        app()->instance('tenant_id', $cliente->id);
        try {
            return $fn();
        } finally {
            app()->forgetInstance('tenant_id');
        }
    }

    private function presentClient(Tenant $cliente): array
    {
        return ['id' => $cliente->id, 'nome' => $cliente->nome];
    }

    private function ensureMenuBelongs(Tenant $cliente, Menu $menu): void
    {
        if ((int) $menu->tenant_id !== (int) $cliente->id) {
            abort(404);
        }
    }

    /** Item pertence ao cliente se o seu menu pertence ao cliente. */
    private function ensureItemBelongs(Tenant $cliente, MenuItem $item): void
    {
        $menu = Menu::find($item->menu_id);
        if (! $menu || (int) $menu->tenant_id !== (int) $cliente->id) {
            abort(404);
        }
    }

    public function index(Tenant $cliente)
    {
        $menus = Menu::where('tenant_id', $cliente->id)
            ->with(['items' => fn ($q) => $q->orderBy('order_column')])
            ->orderBy('local')
            ->orderBy('nome')
            ->get();

        return Inertia::render('Master/Clientes/Cms/Menus/Index', [
            'cliente' => $this->presentClient($cliente),
            'menus' => $menus,
        ]);
    }

    public function storeItem(Request $request, Tenant $cliente, Menu $menu)
    {
        $this->ensureMenuBelongs($cliente, $menu);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:cms_menu_items,id'],
        ]);

        $this->withClientContext($cliente, fn () => MenuItem::create([
            ...$data,
            'menu_id' => $menu->id,
            'target' => $data['target'] ?? '_self',
            'is_active' => true,
            'order_column' => ($menu->items()->max('order_column') ?? -1) + 1,
        ]));

        return back()->with('success', 'Item adicionado.');
    }

    public function updateItem(Request $request, Tenant $cliente, MenuItem $item)
    {
        $this->ensureItemBelongs($cliente, $item);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $this->withClientContext($cliente, fn () => $item->update($data));

        return back()->with('success', 'Item atualizado.');
    }

    public function destroyItem(Tenant $cliente, MenuItem $item)
    {
        $this->ensureItemBelongs($cliente, $item);

        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function reorder(Tenant $cliente, Menu $menu, Request $request)
    {
        $this->ensureMenuBelongs($cliente, $menu);

        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->order as $i => $itemId) {
            MenuItem::where('id', $itemId)
                ->where('menu_id', $menu->id)
                ->update(['order_column' => $i]);
        }

        return back();
    }
}
