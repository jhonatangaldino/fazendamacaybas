<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Endpoint inline de categoria — usado por wizards que precisam criar
 * uma categoria (financeira, estoque, etc.) sem tirar o usuário do fluxo.
 *
 * Princípio: fluxo guiado = funcionalidade interligada. Se na hora de
 * lançar despesa o usuário percebe que falta a categoria, ele cria aqui,
 * o modal fecha, o dropdown atualiza e ele continua o lançamento.
 */
class CategoryController extends Controller
{
    public function storeInline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            // Aceita tipos legados (financeiro/estoque/outro) E os tipos
            // específicos usados pelas transações (financeiro_receita /
            // financeiro_despesa). Form passa o tipo certo conforme o
            // contexto onde foi disparado (Despesa wizard / Receita wizard).
            'tipo' => ['required', 'in:financeiro,financeiro_receita,financeiro_despesa,estoque,outro'],
        ]);

        // Slug único por tipo — se já existir, acrescenta sufixo numérico.
        $baseSlug = Str::slug($data['nome']);
        $slug = $baseSlug;
        $n = 2;
        while (Category::where('tipo', $data['tipo'])->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $n++;
        }

        $category = Category::create([
            'nome' => $data['nome'],
            'slug' => $slug,
            'tipo' => $data['tipo'],
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $category->id,
            'nome' => $category->nome,
            'tipo' => $category->tipo,
        ]);
    }
}
