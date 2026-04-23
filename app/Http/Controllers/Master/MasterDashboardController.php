<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MasterDashboardController — M1
 *
 * Placeholder do dashboard da plataforma SaaS. Em M2 ganha o MasterLayout
 * definitivo; em M3+ recebe widgets reais (MRR, tenants ativos, inadimplência).
 *
 * Esta fase entrega apenas a "casca" necessária para validar:
 *   - Rota /master/dashboard existe (name master.dashboard)
 *   - Middleware EnforceMaster autoriza apenas master puro
 *   - User::homeUrl() redireciona master logado para cá
 *   - EnsureTenantUser redireciona master que tentou /admin/* para cá
 */
class MasterDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Master/Dashboard');
    }
}
