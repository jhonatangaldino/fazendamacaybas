<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub de ações — a "porta de entrada" do sistema.
 *
 * Em vez de abrir num menu técnico por módulo, o usuário vê
 * "O que você quer fazer?" com as 27 operações reais da fazenda
 * organizadas por frequência (todo dia / essa semana / safra / ocasional).
 *
 * O Hub é pura renderização — não carrega dados. A filtragem por
 * permissão acontece no front (usando `auth.user.permissions`
 * já exposto pelo HandleInertiaRequests). Sem permissão, o card
 * simplesmente não aparece.
 *
 * Decisão de produto: o Dashboard antigo continua em /admin/dashboard
 * (painel de números). Este Hub é a nova raiz de /admin.
 */
class HubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Hub');
    }
}
