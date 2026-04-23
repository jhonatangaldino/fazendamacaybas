<?php

use App\Http\Controllers\Admin\Agricultural\AgriculturalController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FarmSelectionController;
use App\Http\Controllers\Admin\MenuUsageController;
use App\Http\Controllers\Admin\PendingPaymentController;
use App\Http\Controllers\Master\Clientes\Cms\CmsController as ClientesCmsController;
use App\Http\Controllers\Master\Clientes\Cms\MenuController as ClientesCmsMenuController;
use App\Http\Controllers\Master\Clientes\Cms\SettingsController as ClientesCmsSettingsController;
use App\Http\Controllers\Master\Cms\CmsController as MasterCmsController;
use App\Http\Controllers\Master\Cms\MenuController as MasterCmsMenuController;
use App\Http\Controllers\Master\Cms\SettingsController as MasterCmsSettingsController;
use App\Http\Controllers\Master\FarmController as MasterFarmController;
use App\Http\Controllers\Master\ImpersonationController;
use App\Http\Controllers\Master\InvoiceController;
use App\Http\Controllers\Master\MasterDashboardController;
use App\Http\Controllers\Master\PlanController;
use App\Http\Controllers\Master\SubscriptionController;
use App\Http\Controllers\Master\TenantController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\Financial\FinancialIndexController;
use App\Http\Controllers\Admin\Financial\FinancialTransactionController;
use App\Http\Controllers\Admin\Livestock\AnimalController;
use App\Http\Controllers\Admin\Livestock\LivestockIndexController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Stock\StockItemController;
use App\Http\Controllers\Admin\Stock\StockMovementController;
use App\Http\Controllers\Admin\Stock\WarehouseController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Vehicle\VehicleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ===================== SITE PÚBLICO =====================
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/health', [SiteController::class, 'health'])->name('site.health');

// ===================== AUTENTICAÇÃO =====================
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('alterar-senha', [ChangePasswordController::class, 'create'])->name('password.change');
    Route::put('alterar-senha', [ChangePasswordController::class, 'update']);

    // Avatar próprio — qualquer usuário autenticado pode trocar a própria foto
    Route::post('meu-avatar', function (Request $req) {
        return app(\App\Http\Controllers\Admin\UserController::class)->uploadAvatar($req, $req->user());
    })->name('me.avatar.upload');
    Route::delete('meu-avatar', function (Request $req) {
        return app(\App\Http\Controllers\Admin\UserController::class)->removeAvatar($req, $req->user());
    })->name('me.avatar.remove');
});

// ===================== ÁREA MASTER (M1) =====================
// Grupo /master/* — administração da plataforma SaaS.
// Middleware enforce.master exige: user autenticado, tenant_id NULL,
// role `admin_master`. Qualquer outro caso = 403 (sem redirect,
// para evitar loops com o tenant.user.only do grupo admin).
Route::middleware(['auth', 'enforce.master'])->prefix('master')->name('master.')->group(function () {
    Route::get('dashboard', [MasterDashboardController::class, 'index'])->name('dashboard');

    // M3 — CRUD de tenants (clientes do SaaS)
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/novo', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('tenants/{tenant}/editar', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('tenants/{tenant}/toggle', [TenantController::class, 'toggle'])->name('tenants.toggle');

    // M5 — Impersonação. `start` fica junto do tenant (POST /master/tenants/{tenant}/impersonate),
    // `exit` em rota própria para ser chamável de qualquer contexto (admin ou master)
    // via banner. Ambas passam por auth+enforce.master (master com tenant_id=NULL).
    Route::post('tenants/{tenant}/impersonate', [ImpersonationController::class, 'start'])
        ->name('tenants.impersonate');
    Route::post('impersonation/sair', [ImpersonationController::class, 'exit'])
        ->name('impersonation.exit');

    // M6 — Planos (catálogo global da plataforma)
    Route::get('planos', [PlanController::class, 'index'])->name('planos.index');
    Route::get('planos/novo', [PlanController::class, 'create'])->name('planos.create');
    Route::post('planos', [PlanController::class, 'store'])->name('planos.store');
    Route::get('planos/{plan}/editar', [PlanController::class, 'edit'])->name('planos.edit');
    Route::put('planos/{plan}', [PlanController::class, 'update'])->name('planos.update');
    Route::post('planos/{plan}/toggle', [PlanController::class, 'toggle'])->name('planos.toggle');

    // M6 — Cobranças (listagem global + ações de status)
    Route::get('cobrancas', [InvoiceController::class, 'index'])->name('cobrancas.index');
    Route::get('cobrancas/{invoice}/pix', [InvoiceController::class, 'showPix'])->name('cobrancas.pix');
    Route::post('cobrancas/{invoice}/marcar-paga', [InvoiceController::class, 'markPaid'])->name('cobrancas.mark-paid');
    Route::post('cobrancas/{invoice}/marcar-pendente', [InvoiceController::class, 'markPending'])->name('cobrancas.mark-pending');

    // M6 — Assinatura + cobranças POR tenant
    Route::get('tenants/{tenant}/assinatura', [SubscriptionController::class, 'show'])->name('tenants.subscription.show');
    Route::put('tenants/{tenant}/assinatura', [SubscriptionController::class, 'update'])->name('tenants.subscription.update');
    Route::post('tenants/{tenant}/assinatura/cancelar', [SubscriptionController::class, 'cancel'])->name('tenants.subscription.cancel');
    Route::post('tenants/{tenant}/cobrancas', [InvoiceController::class, 'store'])->name('tenants.invoices.store');

    // M7 — CMS da landing pública (migrado de /admin/cms em M7).
    // Platform-level: sem tenant_id/farm_id. Guardado apenas por enforce.master
    // (nunca mais precisa de role:admin_master + permission:cms.* do legado,
    // pois o grupo já garante acesso exclusivo ao master).
    Route::get('cms', [MasterCmsController::class, 'index'])->name('cms.index');
    Route::get('cms/pagina/{page}', [MasterCmsController::class, 'edit'])->name('cms.edit');
    Route::put('cms/pagina/{page}', [MasterCmsController::class, 'updatePage'])->name('cms.update-page');
    Route::put('cms/secao/{section}/rascunho', [MasterCmsController::class, 'saveSectionDraft'])->name('cms.section.draft');
    Route::post('cms/secao/{section}/publicar', [MasterCmsController::class, 'publishSection'])->name('cms.section.publish');
    Route::post('cms/secao/{section}/toggle', [MasterCmsController::class, 'toggleActive'])->name('cms.section.toggle');
    Route::post('cms/pagina/{page}/publicar-tudo', [MasterCmsController::class, 'publishAll'])->name('cms.publish-all');
    Route::post('cms/pagina/{page}/reordenar', [MasterCmsController::class, 'reorderSections'])->name('cms.reorder');
    Route::post('cms/upload-imagem', [MasterCmsController::class, 'uploadImage'])->name('cms.upload-image');

    Route::get('cms/menus', [MasterCmsMenuController::class, 'index'])->name('cms.menus.index');
    Route::post('cms/menus/{menu}/items', [MasterCmsMenuController::class, 'storeItem'])->name('cms.menus.items.store');
    Route::put('cms/menus/items/{item}', [MasterCmsMenuController::class, 'updateItem'])->name('cms.menus.items.update');
    Route::delete('cms/menus/items/{item}', [MasterCmsMenuController::class, 'destroyItem'])->name('cms.menus.items.destroy');
    Route::post('cms/menus/{menu}/reordenar', [MasterCmsMenuController::class, 'reorder'])->name('cms.menus.reorder');

    Route::get('cms/configuracoes', [MasterCmsSettingsController::class, 'index'])->name('cms.settings');
    Route::put('cms/configuracoes', [MasterCmsSettingsController::class, 'update'])->name('cms.settings.update');
    Route::post('cms/configuracoes/upload', [MasterCmsSettingsController::class, 'uploadFile'])->name('cms.settings.upload');
    Route::post('cms/configuracoes/remover-arquivo', [MasterCmsSettingsController::class, 'removeFile'])->name('cms.settings.remove-file');

    // CMS por cliente (CMS.A) — master edita o CMS/landing de UM cliente específico.
    // Paralelo ao CMS legado (/master/cms/*), que continua funcionando para
    // compatibilidade (base de dados com 1 cliente). Quando um 2º cliente for
    // cadastrado, o fluxo correto é entrar por /master/clientes/{cliente}/cms.
    Route::prefix('clientes/{cliente}')->name('clientes.')->group(function () {
        // Páginas
        Route::get('cms', [ClientesCmsController::class, 'index'])->name('cms.index');
        Route::get('cms/pagina/{page}', [ClientesCmsController::class, 'edit'])->name('cms.edit');
        Route::put('cms/pagina/{page}', [ClientesCmsController::class, 'updatePage'])->name('cms.update-page');
        Route::put('cms/secao/{section}/rascunho', [ClientesCmsController::class, 'saveSectionDraft'])->name('cms.section.draft');
        Route::post('cms/secao/{section}/publicar', [ClientesCmsController::class, 'publishSection'])->name('cms.section.publish');
        Route::post('cms/secao/{section}/toggle', [ClientesCmsController::class, 'toggleActive'])->name('cms.section.toggle');
        Route::post('cms/pagina/{page}/publicar-tudo', [ClientesCmsController::class, 'publishAll'])->name('cms.publish-all');
        Route::post('cms/pagina/{page}/reordenar', [ClientesCmsController::class, 'reorderSections'])->name('cms.reorder');
        Route::post('cms/upload-imagem', [ClientesCmsController::class, 'uploadImage'])->name('cms.upload-image');

        // Menus
        Route::get('cms/menus', [ClientesCmsMenuController::class, 'index'])->name('cms.menus.index');
        Route::post('cms/menus/{menu}/items', [ClientesCmsMenuController::class, 'storeItem'])->name('cms.menus.items.store');
        Route::put('cms/menus/items/{item}', [ClientesCmsMenuController::class, 'updateItem'])->name('cms.menus.items.update');
        Route::delete('cms/menus/items/{item}', [ClientesCmsMenuController::class, 'destroyItem'])->name('cms.menus.items.destroy');
        Route::post('cms/menus/{menu}/reordenar', [ClientesCmsMenuController::class, 'reorder'])->name('cms.menus.reorder');

        // Configurações (settings per-cliente)
        Route::get('cms/configuracoes', [ClientesCmsSettingsController::class, 'index'])->name('cms.settings');
        Route::put('cms/configuracoes', [ClientesCmsSettingsController::class, 'update'])->name('cms.settings.update');
        Route::post('cms/configuracoes/upload', [ClientesCmsSettingsController::class, 'uploadFile'])->name('cms.settings.upload');
        Route::post('cms/configuracoes/remover-arquivo', [ClientesCmsSettingsController::class, 'removeFile'])->name('cms.settings.remove-file');
    });

    // M4 — Fazendas do tenant (sempre aninhadas). scopeBindings() garante
    // que {farm} pertença a {tenant} via Tenant::farms() relation — 404 se
    // alguém tentar GET /master/tenants/1/fazendas/42/editar onde 42 é
    // farm de outro tenant.
    Route::prefix('tenants/{tenant}')->name('tenants.')->scopeBindings()->group(function () {
        Route::get('fazendas', [MasterFarmController::class, 'index'])->name('farms.index');
        Route::get('fazendas/nova', [MasterFarmController::class, 'create'])->name('farms.create');
        Route::post('fazendas', [MasterFarmController::class, 'store'])->name('farms.store');
        Route::get('fazendas/{farm}/editar', [MasterFarmController::class, 'edit'])->name('farms.edit');
        Route::put('fazendas/{farm}', [MasterFarmController::class, 'update'])->name('farms.update');
        Route::post('fazendas/{farm}/toggle', [MasterFarmController::class, 'toggle'])->name('farms.toggle');
    });
});

// ===================== ÁREA ADMIN =====================
// Stack: auth → tenant.user.only → enforce.subscription → enforce.farm → permission:*
// - tenant.user.only (M1): master puro é redirecionado para /master/dashboard
//   ANTES de qualquer query de farm. Economiza round-trip e garante separação.
// - enforce.subscription (billing): bloqueia tenant overdue, redireciona
//   para admin.pagamento-pendente. Master (mesmo impersonando) não é afetado.
//   Ordem IMPORTANTE: antes do enforce.farm — se está bloqueado, nem
//   faz sentido resolver farm.
// - enforce.farm (R2.6): resolve app('farm_id') ou redireciona ao seletor
//   quando o tenant tiver múltiplas fazendas. Rotas fazenda.select/switch
//   estão na whitelist interna do middleware.
Route::middleware(['auth', 'tenant.user.only', 'enforce.subscription', 'enforce.farm'])
    ->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    // Tela "acesso bloqueado por inadimplência" — whitelisted no enforce.subscription.
    // Única rota /admin acessível para tenant com subscription overdue.
    // Exibe PIX copia-e-cola das invoices em aberto do próprio tenant.
    Route::get('pagamento-pendente', [PendingPaymentController::class, 'show'])
        ->name('pagamento-pendente');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:operational.dashboard.view')->name('dashboard');

    // ------- FAZENDA (seleção e troca) -------
    // Rotas whitelistadas pelo EnforceFarm — acessíveis mesmo sem farm ativa.
    Route::get('fazenda/selecionar', [FarmSelectionController::class, 'select'])->name('fazenda.select');
    Route::post('fazenda/trocar', [FarmSelectionController::class, 'switch'])->name('fazenda.switch');

    // Contador de uso dos itens da sidebar — alimenta a ordenação "mais usados no topo"
    Route::post('menu-usage', [MenuUsageController::class, 'bump'])->name('menu-usage.bump');

    // ------- USUÁRIOS -------
    Route::middleware('permission:operational.usuarios.view')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/novo', [UserController::class, 'create'])->middleware('permission:operational.usuarios.create')->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->middleware('permission:operational.usuarios.create')->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('permission:operational.usuarios.update')->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->middleware('permission:operational.usuarios.update')->name('users.update');
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:operational.usuarios.delete')->name('users.destroy');
        Route::post('usuarios/{user}/resetar-senha', [UserController::class, 'resetPassword'])->middleware('permission:operational.usuarios.reset_password')->name('users.reset-password');
        Route::post('usuarios/{user}/avatar', [UserController::class, 'uploadAvatar'])->name('users.avatar.upload');
        Route::delete('usuarios/{user}/avatar', [UserController::class, 'removeAvatar'])->name('users.avatar.remove');
    });

    // Perfis e permissões
    Route::middleware('permission:platform.roles.view')->group(function () {
        Route::get('perfis', [RoleController::class, 'index'])->name('roles.index');
        Route::post('perfis', [RoleController::class, 'store'])->middleware('permission:platform.roles.manage')->name('roles.store');
        Route::put('perfis/{role}', [RoleController::class, 'update'])->middleware('permission:platform.roles.manage')->name('roles.update');
        Route::delete('perfis/{role}', [RoleController::class, 'destroy'])->middleware('permission:platform.roles.manage')->name('roles.destroy');
    });

    // CMS migrado para /master/cms em M7 (é platform-level, não tenant-level).

    // ------- PARCEIROS -------
    Route::middleware('permission:operational.parceiros.view')->group(function () {
        Route::get('parceiros', [PartnerController::class, 'index'])->name('parceiros.index');
        Route::get('parceiros/novo', [PartnerController::class, 'create'])->middleware('permission:operational.parceiros.create')->name('parceiros.create');
        Route::post('parceiros', [PartnerController::class, 'store'])->middleware('permission:operational.parceiros.create')->name('parceiros.store');
        Route::get('parceiros/{parceiro}/editar', [PartnerController::class, 'edit'])->middleware('permission:operational.parceiros.update')->name('parceiros.edit');
        Route::put('parceiros/{parceiro}', [PartnerController::class, 'update'])->middleware('permission:operational.parceiros.update')->name('parceiros.update');
        Route::delete('parceiros/{parceiro}', [PartnerController::class, 'destroy'])->middleware('permission:operational.parceiros.delete')->name('parceiros.destroy');
    });

    // ------- FINANCEIRO -------
    Route::middleware('permission:operational.financeiro.view')->group(function () {
        Route::get('financeiro', FinancialIndexController::class)->name('financeiro.index');
        Route::get('financeiro/transacoes', [FinancialTransactionController::class, 'index'])->name('financeiro.transacoes.index');
        Route::get('financeiro/transacoes/novo', [FinancialTransactionController::class, 'create'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.transacoes.create');
        Route::post('financeiro/transacoes', [FinancialTransactionController::class, 'store'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.transacoes.store');
        Route::get('financeiro/transacoes/{transacao}/editar', [FinancialTransactionController::class, 'edit'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.edit');
        Route::put('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'update'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.update');
        Route::delete('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'destroy'])->middleware('permission:operational.financeiro.transacoes.delete')->name('financeiro.transacoes.destroy');
        Route::post('financeiro/transacoes/{transacao}/pagar', [FinancialTransactionController::class, 'pay'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.pay');
    });

    // ------- REBANHO -------
    Route::middleware('permission:operational.rebanho.view')->group(function () {
        Route::get('rebanho', LivestockIndexController::class)->name('rebanho.index');
        Route::get('rebanho/animais', [AnimalController::class, 'index'])->name('rebanho.animais.index');
        Route::get('rebanho/animais/novo', [AnimalController::class, 'create'])->middleware('permission:operational.rebanho.animais.create')->name('rebanho.animais.create');
        Route::post('rebanho/animais', [AnimalController::class, 'store'])->middleware('permission:operational.rebanho.animais.create')->name('rebanho.animais.store');
        Route::get('rebanho/animais/{animal}/editar', [AnimalController::class, 'edit'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.edit');
        Route::put('rebanho/animais/{animal}', [AnimalController::class, 'update'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.update');
        Route::delete('rebanho/animais/{animal}', [AnimalController::class, 'destroy'])->middleware('permission:operational.rebanho.animais.delete')->name('rebanho.animais.destroy');
        Route::post('rebanho/animais/{animal}/foto', [AnimalController::class, 'uploadPhoto'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.foto.upload');
        Route::delete('rebanho/animais/{animal}/foto', [AnimalController::class, 'removePhoto'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.foto.remove');
        Route::get('rebanho/animais/{animal}', [AnimalController::class, 'show'])->name('rebanho.animais.show');
        Route::post('rebanho/animais/{animal}/eventos', [AnimalController::class, 'storeEvent'])->middleware('permission:operational.rebanho.eventos.create')->name('rebanho.animais.eventos.store');
        Route::post('rebanho/animais/vender-lote', [AnimalController::class, 'sellBatch'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.vender-lote');
        Route::delete('rebanho/animais/{animal}/eventos/{event}', [AnimalController::class, 'destroyEvent'])->middleware('permission:operational.rebanho.eventos.delete')->name('rebanho.animais.eventos.destroy');
    });

    // ------- AGRÍCOLA -------
    Route::middleware('permission:operational.agricola.view')->group(function () {
        Route::get('agricola', [AgriculturalController::class, 'index'])->name('agricola.index');

        Route::get('agricola/talhoes', [AgriculturalController::class, 'fields'])->name('agricola.talhoes.index');
        Route::post('agricola/talhoes', [AgriculturalController::class, 'fieldStore'])->middleware('permission:operational.agricola.talhoes.create')->name('agricola.talhoes.store');
        Route::put('agricola/talhoes/{field}', [AgriculturalController::class, 'fieldUpdate'])->middleware('permission:operational.agricola.talhoes.update')->name('agricola.talhoes.update');
        Route::delete('agricola/talhoes/{field}', [AgriculturalController::class, 'fieldDestroy'])->middleware('permission:operational.agricola.talhoes.delete')->name('agricola.talhoes.destroy');
        Route::post('agricola/talhoes/{field}/toggle', [AgriculturalController::class, 'fieldToggle'])->middleware('permission:operational.agricola.talhoes.update')->name('agricola.talhoes.toggle');

        Route::get('agricola/culturas', [AgriculturalController::class, 'crops'])->name('agricola.culturas.index');
        Route::post('agricola/culturas', [AgriculturalController::class, 'cropStore'])->name('agricola.culturas.store');
        Route::delete('agricola/culturas/{crop}', [AgriculturalController::class, 'cropDestroy'])->name('agricola.culturas.destroy');
        Route::post('agricola/safras', [AgriculturalController::class, 'seasonStore'])->name('agricola.safras.store');
        Route::delete('agricola/safras/{season}', [AgriculturalController::class, 'seasonDestroy'])->name('agricola.safras.destroy');

        Route::get('agricola/plantios', [AgriculturalController::class, 'plantings'])->name('agricola.plantios.index');
        Route::post('agricola/plantios', [AgriculturalController::class, 'plantingStore'])->middleware('permission:operational.agricola.plantios.create')->name('agricola.plantios.store');
        Route::put('agricola/plantios/{planting}', [AgriculturalController::class, 'plantingUpdate'])->middleware('permission:operational.agricola.plantios.update')->name('agricola.plantios.update');
        Route::delete('agricola/plantios/{planting}', [AgriculturalController::class, 'plantingDestroy'])->middleware('permission:operational.agricola.plantios.delete')->name('agricola.plantios.destroy');

        Route::get('agricola/colheitas', [AgriculturalController::class, 'harvests'])->name('agricola.colheitas.index');
        Route::post('agricola/colheitas', [AgriculturalController::class, 'harvestStore'])->middleware('permission:operational.agricola.colheitas.create')->name('agricola.colheitas.store');
        Route::delete('agricola/colheitas/{harvest}', [AgriculturalController::class, 'harvestDestroy'])->middleware('permission:operational.agricola.colheitas.delete')->name('agricola.colheitas.destroy');

        Route::get('agricola/aplicacoes', [AgriculturalController::class, 'applications'])->name('agricola.aplicacoes.index');
        Route::post('agricola/aplicacoes', [AgriculturalController::class, 'applicationStore'])->middleware('permission:operational.agricola.aplicacoes.create')->name('agricola.aplicacoes.store');
        Route::delete('agricola/aplicacoes/{application}', [AgriculturalController::class, 'applicationDestroy'])->middleware('permission:operational.agricola.aplicacoes.delete')->name('agricola.aplicacoes.destroy');
    });

    // ------- ESTOQUE -------
    Route::middleware('permission:operational.estoque.view')->group(function () {
        Route::get('estoque', fn () => Inertia::render('Admin/Stock/Index'))->name('estoque.index');

        Route::get('estoque/itens', [StockItemController::class, 'index'])->name('estoque.itens.index');
        Route::get('estoque/itens/lookup-barcode', [StockItemController::class, 'lookupByBarcode'])->name('estoque.itens.lookup-barcode');
        Route::get('estoque/itens/novo', [StockItemController::class, 'create'])->middleware('permission:operational.estoque.itens.create')->name('estoque.itens.create');
        Route::post('estoque/itens', [StockItemController::class, 'store'])->middleware('permission:operational.estoque.itens.create')->name('estoque.itens.store');
        Route::get('estoque/itens/{item}/editar', [StockItemController::class, 'edit'])->middleware('permission:operational.estoque.itens.update')->name('estoque.itens.edit');
        Route::put('estoque/itens/{item}', [StockItemController::class, 'update'])->middleware('permission:operational.estoque.itens.update')->name('estoque.itens.update');
        Route::delete('estoque/itens/{item}', [StockItemController::class, 'destroy'])->middleware('permission:operational.estoque.itens.delete')->name('estoque.itens.destroy');
        Route::post('estoque/itens/{item}/toggle', [StockItemController::class, 'toggle'])->middleware('permission:operational.estoque.itens.update')->name('estoque.itens.toggle');

        Route::get('estoque/movimentos', [StockMovementController::class, 'index'])->name('estoque.movimentos.index');
        Route::post('estoque/movimentos', [StockMovementController::class, 'store'])->middleware('permission:operational.estoque.movimentos.create')->name('estoque.movimentos.store');
        Route::delete('estoque/movimentos/{movement}', [StockMovementController::class, 'destroy'])->middleware('permission:operational.estoque.movimentos.delete')->name('estoque.movimentos.destroy');

        Route::get('estoque/armazens', [WarehouseController::class, 'index'])->name('estoque.armazens.index');
        Route::post('estoque/armazens', [WarehouseController::class, 'store'])->middleware('permission:operational.estoque.armazens.create')->name('estoque.armazens.store');
        Route::put('estoque/armazens/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:operational.estoque.armazens.update')->name('estoque.armazens.update');
        Route::delete('estoque/armazens/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:operational.estoque.armazens.delete')->name('estoque.armazens.destroy');
        Route::post('estoque/armazens/{warehouse}/toggle', [WarehouseController::class, 'toggle'])->middleware('permission:operational.estoque.armazens.update')->name('estoque.armazens.toggle');
    });

    // ------- MÁQUINAS -------
    Route::middleware('permission:operational.maquinas.view')->group(function () {
        Route::get('maquinas', [VehicleController::class, 'index'])->name('maquinas.index');

        Route::get('maquinas/veiculos', [VehicleController::class, 'vehicles'])->name('maquinas.veiculos.index');
        Route::post('maquinas/veiculos', [VehicleController::class, 'vehicleStore'])->middleware('permission:operational.maquinas.veiculos.create')->name('maquinas.veiculos.store');
        Route::put('maquinas/veiculos/{vehicle}', [VehicleController::class, 'vehicleUpdate'])->middleware('permission:operational.maquinas.veiculos.update')->name('maquinas.veiculos.update');
        Route::delete('maquinas/veiculos/{vehicle}', [VehicleController::class, 'vehicleDestroy'])->middleware('permission:operational.maquinas.veiculos.delete')->name('maquinas.veiculos.destroy');
        Route::post('maquinas/veiculos/{vehicle}/toggle', [VehicleController::class, 'vehicleToggle'])->middleware('permission:operational.maquinas.veiculos.update')->name('maquinas.veiculos.toggle');

        Route::get('maquinas/manutencoes', [VehicleController::class, 'maintenance'])->name('maquinas.manutencoes.index');
        Route::post('maquinas/manutencoes', [VehicleController::class, 'maintenanceStore'])->middleware('permission:operational.maquinas.manutencoes.create')->name('maquinas.manutencoes.store');
        Route::put('maquinas/manutencoes/{order}', [VehicleController::class, 'maintenanceUpdate'])->middleware('permission:operational.maquinas.manutencoes.update')->name('maquinas.manutencoes.update');
        Route::delete('maquinas/manutencoes/{order}', [VehicleController::class, 'maintenanceDestroy'])->middleware('permission:operational.maquinas.manutencoes.delete')->name('maquinas.manutencoes.destroy');
    });

    // ------- FUNCIONÁRIOS -------
    Route::middleware('permission:operational.funcionarios.view')->group(function () {
        Route::get('funcionarios', [EmployeeController::class, 'index'])->name('funcionarios.index');
        Route::post('funcionarios', [EmployeeController::class, 'store'])->middleware('permission:operational.funcionarios.cadastro.create')->name('funcionarios.store');
        Route::put('funcionarios/{employee}', [EmployeeController::class, 'update'])->middleware('permission:operational.funcionarios.cadastro.update')->name('funcionarios.update');
        Route::delete('funcionarios/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:operational.funcionarios.cadastro.delete')->name('funcionarios.destroy');
        Route::post('funcionarios/{employee}/toggle', [EmployeeController::class, 'toggle'])->middleware('permission:operational.funcionarios.cadastro.update')->name('funcionarios.toggle');
    });

    // ------- TAREFAS -------
    Route::middleware('permission:operational.funcionarios.tarefas.view')->group(function () {
        Route::get('tarefas', [TaskController::class, 'index'])->name('tarefas.index');
        Route::post('tarefas', [TaskController::class, 'store'])->middleware('permission:operational.funcionarios.tarefas.create')->name('tarefas.store');
        Route::put('tarefas/{task}', [TaskController::class, 'update'])->middleware('permission:operational.funcionarios.tarefas.update')->name('tarefas.update');
        Route::delete('tarefas/{task}', [TaskController::class, 'destroy'])->middleware('permission:operational.funcionarios.tarefas.delete')->name('tarefas.destroy');
        Route::post('tarefas/{task}/concluir', [TaskController::class, 'complete'])->middleware('permission:operational.funcionarios.tarefas.update')->name('tarefas.complete');
        Route::post('tarefas/{task}/reabrir', [TaskController::class, 'reopen'])->middleware('permission:operational.funcionarios.tarefas.update')->name('tarefas.reopen');
        Route::post('tarefas/checklist/{item}/toggle', [TaskController::class, 'toggleChecklistItem'])->middleware('permission:operational.funcionarios.tarefas.update')->name('tarefas.checklist.toggle');
    });

    // ------- DOCUMENTOS -------
    Route::middleware('permission:operational.documentos.view')->group(function () {
        Route::get('documentos', [DocumentController::class, 'index'])->name('documentos.index');
        Route::post('documentos', [DocumentController::class, 'store'])->middleware('permission:operational.documentos.create')->name('documentos.store');
        Route::delete('documentos/{document}', [DocumentController::class, 'destroy'])->middleware('permission:operational.documentos.delete')->name('documentos.destroy');
        Route::post('documentos/categorias', [DocumentController::class, 'categoryStore'])->middleware('permission:operational.documentos.create')->name('documentos.categorias.store');
        Route::delete('documentos/categorias/{category}', [DocumentController::class, 'categoryDestroy'])->middleware('permission:operational.documentos.delete')->name('documentos.categorias.destroy');
    });

    // ------- RELATÓRIOS -------
    Route::get('relatorios', [ReportController::class, 'index'])
        ->middleware('permission:operational.relatorios.view')->name('relatorios.index');
});
