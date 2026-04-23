<?php

namespace App\Http\Controllers\Master\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * MenuController — M7 (migrado de Admin\Cms → Master\Cms)
 *
 * Gerencia os menus públicos da landing (cabeçalho, rodapé). Platform-level.
 * Lógica 1:1 com a versão antiga.
 */
class MenuController extends Controller
{
    public function index()
    {
        return Inertia::render('Master/Cms/Menus/Index', [
            'menus' => Menu::with(['items' => fn ($q) => $q->orderBy('order_column')])
                ->orderBy('local')
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function storeItem(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:cms_menu_items,id'],
        ]);

        MenuItem::create([
            ...$data,
            'menu_id' => $menu->id,
            'target' => $data['target'] ?? '_self',
            'is_active' => true,
            'order_column' => ($menu->items()->max('order_column') ?? -1) + 1,
        ]);

        return back()->with('success', 'Item adicionado.');
    }

    public function updateItem(Request $request, MenuItem $item)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['nullable', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $item->update($data);

        return back()->with('success', 'Item atualizado.');
    }

    public function destroyItem(MenuItem $item)
    {
        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function reorder(Menu $menu, Request $request)
    {
        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->order as $i => $itemId) {
            MenuItem::where('id', $itemId)->where('menu_id', $menu->id)->update(['order_column' => $i]);
        }

        return back();
    }
}
