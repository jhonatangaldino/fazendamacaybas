<?php

use App\Http\Controllers\Admin\Agricultural\AgriculturalController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HubController;
use App\Http\Controllers\Admin\Wizards\PesagemWizardController;
use App\Http\Controllers\Admin\Wizards\DespesaWizardController;
use App\Http\Controllers\Admin\Wizards\ReceitaWizardController;
use App\Http\Controllers\Admin\Wizards\AplicacaoWizardController;
use App\Http\Controllers\Admin\Wizards\ManutencaoWizardController;
use App\Http\Controllers\Admin\Wizards\EventoRebanhoWizardController;
use App\Http\Controllers\Admin\Wizards\CadastroAnimalWizardController;
use App\Http\Controllers\Admin\Wizards\ColheitaWizardController;
use App\Http\Controllers\Admin\Wizards\DocumentoWizardController;
use App\Http\Controllers\Admin\Wizards\FuncionarioWizardController;
use App\Http\Controllers\Admin\Wizards\PlantioWizardController;
use App\Http\Controllers\Admin\Wizards\TarefaWizardController;
use App\Http\Controllers\Admin\Wizards\EstoqueWizardController;
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
use App\Http\Controllers\Master\InvoiceWizardController;
use App\Http\Controllers\Master\BillingConfigController;
use App\Http\Controllers\Master\MasterDashboardController;
use App\Http\Controllers\Master\ActivityLogController;
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
use App\Http\Controllers\Admin\SaleWizardController;
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
// Landing pública por cliente — resolve Tenant pelo slug (404 automático via
// route-model-binding). O controller faz o bind de app('tenant_id') apenas
// para a vida útil desta request. Nenhum filtro novo de CMS nesta fase.
Route::get('/c/{cliente:slug}', [SiteController::class, 'homeByCliente'])->name('site.home.cliente');
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

    // Auditoria · activity_log com filtros (visibilidade master sobre todos os clientes)
    Route::get('atividades', [ActivityLogController::class, 'index'])->name('atividades.index');
    Route::get('atividades/{atividade}', [ActivityLogController::class, 'show'])->name('atividades.show');

    // M3 — CRUD de tenants (clientes do SaaS)
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/novo', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    // Helper AJAX: aceita URL Google Maps, DMS ou decimal e devolve {lat,lng}
    Route::post('tenants/parse-coords', [TenantController::class, 'parseCoords'])->name('tenants.parse-coords');
    Route::get('tenants/{tenant}/editar', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('tenants/{tenant}/toggle', [TenantController::class, 'toggle'])->name('tenants.toggle');
    // Marcar/desmarcar como tenant master único da plataforma (constraint unique no banco)
    Route::post('tenants/{tenant}/toggle-master', [TenantController::class, 'toggleMaster'])->name('tenants.toggle-master');

    // Gestão de usuários do tenant pelo master — valor operacional: liga
    // cliente reclamando de senha, master reseta direto sem impersonar.
    Route::get('tenants/{tenant}/usuarios', [TenantController::class, 'users'])->name('tenants.users');
    Route::post('tenants/{tenant}/usuarios/inline', [TenantController::class, 'storeUserInline'])->name('tenants.users.inline');
    Route::post('tenants/{tenant}/usuarios/{user}/reset-senha', [TenantController::class, 'resetUserPassword'])->name('tenants.users.reset-password');
    Route::post('tenants/{tenant}/usuarios/{user}/toggle', [TenantController::class, 'toggleUser'])->name('tenants.users.toggle');

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
    // Configurações de cobrança (chave PIX) — antes do {invoice} para não colidir
    Route::get('cobrancas/configuracoes', [BillingConfigController::class, 'index'])->name('cobrancas.configuracoes');
    Route::put('cobrancas/configuracoes', [BillingConfigController::class, 'update'])->name('cobrancas.configuracoes.update');
    // Wizard "Gerar faturas" (mensal ou única) — declarado ANTES da rota
    // /{invoice}/pix para não colidir na resolução de rotas com {invoice} = "gerar".
    Route::get('cobrancas/gerar', [InvoiceWizardController::class, 'create'])->name('cobrancas.wizard.create');
    Route::post('cobrancas/gerar/preview', [InvoiceWizardController::class, 'preview'])->name('cobrancas.wizard.preview');
    Route::post('cobrancas/gerar', [InvoiceWizardController::class, 'store'])->name('cobrancas.wizard.store');
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
        // Onboarding (UX V1): marca o tour de primeiro acesso como concluído
        Route::post('cms/configuracoes/onboarding-completo', [ClientesCmsSettingsController::class, 'completeOnboarding'])->name('cms.settings.onboarding-complete');
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
Route::middleware(['auth', 'tenant.user.only', 'enforce.subscription', 'enforce.farm', 'enforce.feature'])
    ->prefix('admin')->name('admin.')->group(function () {
    // Raiz do admin → Hub "O que você quer fazer?" (nova porta de entrada).
    // Antes redirecionava pro dashboard; agora o dashboard é um destino secundário
    // (painel de números), acessível via sidebar e via link "Abrir painel de números"
    // no rodapé do Hub.
    Route::get('/', fn () => redirect()->route('admin.inicio'));

    // ═════════════════════════════════════════════════════════════════════
    // Tutorial in-app contextual — endpoints REST consumidos pelo TutorialTour.vue
    // ═════════════════════════════════════════════════════════════════════
    Route::prefix('tutorials')->name('tutorials.')->group(function () {
        Route::get('active', [\App\Http\Controllers\Admin\TutorialController::class, 'active'])->name('active');
        Route::post('{key}/complete', [\App\Http\Controllers\Admin\TutorialController::class, 'complete'])->name('complete');
        Route::post('{key}/dismiss', [\App\Http\Controllers\Admin\TutorialController::class, 'dismiss'])->name('dismiss');
        Route::post('{key}/snooze', [\App\Http\Controllers\Admin\TutorialController::class, 'snooze'])->name('snooze');
    });

    // Tela "acesso bloqueado por inadimplência" — whitelisted no enforce.subscription.
    // Única rota /admin acessível para tenant com subscription overdue.
    // Exibe PIX copia-e-cola das invoices em aberto do próprio tenant.
    Route::get('pagamento-pendente', [PendingPaymentController::class, 'show'])
        ->name('pagamento-pendente');

    // Faturas do tenant — somente visualização. Filtra por aparece_em <= today
    // para não revelar faturas futuras antes da hora.
    // BLOCO 4.4 fix: requer permission financeiro.view — veterinário/agronomo
    // não devem ver faturas (audit B4.4 confirmou leak antes do fix)
    Route::get('faturas', [\App\Http\Controllers\Admin\FaturasController::class, 'index'])
        ->middleware('permission:operational.financeiro.view')
        ->name('faturas.index');

    // Tela "Plano não inclui esta funcionalidade" — destino do EnforceFeature.
    // CORE: nunca passa por feature gate.
    Route::get('plano-nao-inclui', function (\Illuminate\Http\Request $request) {
        $key = (string) $request->query('feature', '');
        $cat = \App\Domain\Billing\PlanFeatures::CATALOG;
        return \Inertia\Inertia::render('Admin/PlanoNaoInclui', [
            'feature' => array_merge(
                ['key' => $key],
                $cat[$key] ?? ['nome' => 'Funcionalidade', 'descricao' => 'Este módulo não está incluído no seu plano atual.'],
            ),
        ]);
    })->name('feature-not-available');

    // Hub de ações — porta de entrada do sistema.
    // Não tem `permission:` no middleware: o Hub sempre renderiza; a filtragem
    // das 27 operações por permissão acontece no front. Usuário sem nenhuma
    // permissão vê estado vazio amigável (não 403).
    Route::get('inicio', [HubController::class, 'index'])->name('inicio');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:operational.dashboard.view')->name('dashboard');

    // ═════════════════════════════════════════════════════════════════════
    // Fluxos guiados (wizards) — entrada oficial de cada operação do Hub.
    // Cada controller só expõe dados; o submit reutiliza as rotas existentes
    // (zero duplicação de lógica de negócio).
    // ═════════════════════════════════════════════════════════════════════
    Route::prefix('fluxos')->name('fluxos.')->group(function () {
        Route::get('pesar-animal', [PesagemWizardController::class, 'create'])
            ->middleware('permission:operational.rebanho.eventos.create')->name('pesar-animal');
        Route::get('registrar-despesa', [DespesaWizardController::class, 'create'])
            ->middleware('permission:operational.financeiro.transacoes.create')->name('registrar-despesa');
        Route::post('registrar-despesa', [DespesaWizardController::class, 'store'])
            ->middleware('permission:operational.financeiro.transacoes.create')->name('registrar-despesa.store');

        Route::get('registrar-receita', [ReceitaWizardController::class, 'create'])
            ->middleware('permission:operational.financeiro.transacoes.create')->name('registrar-receita');
        Route::post('registrar-receita', [ReceitaWizardController::class, 'store'])
            ->middleware('permission:operational.financeiro.transacoes.create')->name('registrar-receita.store');
        Route::get('aplicar-produto', [AplicacaoWizardController::class, 'create'])
            ->middleware('permission:operational.agricola.aplicacoes.create')->name('aplicar-produto');
        Route::get('arrumar-maquina', [ManutencaoWizardController::class, 'create'])
            ->middleware('permission:operational.maquinas.manutencoes.create')->name('arrumar-maquina');

        // Onda 2 — EventoRebanho serve 6 cards via ?tipo=X
        Route::get('evento-rebanho', [EventoRebanhoWizardController::class, 'create'])
            ->middleware('permission:operational.rebanho.eventos.create')->name('evento-rebanho');

        Route::get('criar-tarefa', [TarefaWizardController::class, 'create'])
            ->middleware('permission:operational.funcionarios.tarefas.create')->name('criar-tarefa');
        Route::post('criar-tarefa', [TarefaWizardController::class, 'store'])
            ->middleware('permission:operational.funcionarios.tarefas.create')->name('criar-tarefa.store');

        Route::get('receber-mercadoria', [EstoqueWizardController::class, 'receber'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('receber-mercadoria');
        Route::post('receber-mercadoria', [EstoqueWizardController::class, 'storeReceber'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('receber-mercadoria.store');

        Route::get('ajustar-estoque', [EstoqueWizardController::class, 'ajustar'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('ajustar-estoque');
        Route::post('ajustar-estoque', [EstoqueWizardController::class, 'storeAjustar'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('ajustar-estoque.store');

        // Onda final — 4 wizards convertendo CRUD em fluxos guiados:
        // Funcionário, Documento, Plantio, Colheita.
        Route::get('cadastrar-funcionario', [FuncionarioWizardController::class, 'create'])
            ->middleware('permission:operational.funcionarios.cadastro.create')->name('cadastrar-funcionario');
        Route::post('cadastrar-funcionario', [FuncionarioWizardController::class, 'store'])
            ->middleware('permission:operational.funcionarios.cadastro.create')->name('cadastrar-funcionario.store');

        Route::get('anexar-documento', [DocumentoWizardController::class, 'create'])
            ->middleware('permission:operational.documentos.create')->name('anexar-documento');
        Route::post('anexar-documento', [DocumentoWizardController::class, 'store'])
            ->middleware('permission:operational.documentos.create')->name('anexar-documento.store');

        Route::get('registrar-plantio', [PlantioWizardController::class, 'create'])
            ->middleware('permission:operational.agricola.plantios.create')->name('registrar-plantio');
        Route::post('registrar-plantio', [PlantioWizardController::class, 'store'])
            ->middleware('permission:operational.agricola.plantios.create')->name('registrar-plantio.store');
        Route::post('registrar-plantio/talhao/inline', [PlantioWizardController::class, 'fieldInline'])
            ->middleware('permission:operational.agricola.talhoes.create')->name('registrar-plantio.talhao.inline');
        Route::post('registrar-plantio/cultura/inline', [PlantioWizardController::class, 'cropInline'])
            ->middleware('permission:operational.agricola.plantios.create')->name('registrar-plantio.cultura.inline');

        Route::get('registrar-colheita', [ColheitaWizardController::class, 'create'])
            ->middleware('permission:operational.agricola.colheitas.create')->name('registrar-colheita');
        Route::post('registrar-colheita', [ColheitaWizardController::class, 'store'])
            ->middleware('permission:operational.agricola.colheitas.create')->name('registrar-colheita.store');

        // Onda 7 cards finais — saída de estoque + cadastro/compra/nascimento de animal
        Route::get('saida-estoque', [EstoqueWizardController::class, 'saida'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('saida-estoque');
        Route::post('saida-estoque', [EstoqueWizardController::class, 'storeSaida'])
            ->middleware('permission:operational.estoque.movimentos.create')->name('saida-estoque.store');

        // Cadastro de animal — 1 wizard, 3 modos (cadastro / compra / nascimento).
        // O modo vem por query (`?modo=...`) — o Hub anexa via hrefPara.
        Route::get('cadastrar-animal', function (Request $request, CadastroAnimalWizardController $c) {
            return $c->create($request, $request->query('modo', 'cadastro'));
        })->middleware('permission:operational.rebanho.animais.create')->name('cadastrar-animal');

        Route::post('cadastrar-animal', function (Request $request, CadastroAnimalWizardController $c) {
            return $c->store($request, $request->query('modo', 'cadastro'));
        })->middleware('permission:operational.rebanho.animais.create')->name('cadastrar-animal.store');
    });

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
        // Inline JSON — criar parceiro de dentro de outros fluxos (venda, despesa)
        Route::post('parceiros/inline', [PartnerController::class, 'storeInline'])
            ->middleware('permission:operational.parceiros.create')
            ->name('parceiros.inline');
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
        // Inline JSON — criar categoria financeira sem sair do wizard de despesa/receita
        Route::post('financeiro/categorias/inline', [\App\Http\Controllers\Admin\CategoryController::class, 'storeInline'])
            ->middleware('permission:operational.financeiro.transacoes.create')
            ->name('financeiro.categorias.inline');
        Route::get('financeiro/transacoes', [FinancialTransactionController::class, 'index'])->name('financeiro.transacoes.index');
        Route::get('financeiro/transacoes/novo', [FinancialTransactionController::class, 'create'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.transacoes.create');
        Route::post('financeiro/transacoes', [FinancialTransactionController::class, 'store'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.transacoes.store');
        Route::get('financeiro/transacoes/{transacao}/editar', [FinancialTransactionController::class, 'edit'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.edit');
        Route::put('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'update'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.update');
        Route::delete('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'destroy'])->middleware('permission:operational.financeiro.transacoes.delete')->name('financeiro.transacoes.destroy');
        Route::post('financeiro/transacoes/{transacao}/pagar', [FinancialTransactionController::class, 'pay'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.transacoes.pay');

        // BLOCO 4.3 RN3 — CRUD UI de Contas Financeiras (antes só existia via seed/tinker)
        Route::get('financeiro/contas', [\App\Http\Controllers\Admin\Financial\FinancialAccountController::class, 'index'])->name('financeiro.contas.index');
        Route::post('financeiro/contas', [\App\Http\Controllers\Admin\Financial\FinancialAccountController::class, 'store'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.contas.store');
        Route::put('financeiro/contas/{conta}', [\App\Http\Controllers\Admin\Financial\FinancialAccountController::class, 'update'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.contas.update');
        Route::post('financeiro/contas/{conta}/toggle', [\App\Http\Controllers\Admin\Financial\FinancialAccountController::class, 'toggle'])->middleware('permission:operational.financeiro.transacoes.update')->name('financeiro.contas.toggle');
        // RN2 — endpoint inline JSON usado dentro dos wizards Despesa/Receita
        Route::post('financeiro/contas/inline', [\App\Http\Controllers\Admin\Financial\FinancialAccountController::class, 'storeInline'])->middleware('permission:operational.financeiro.transacoes.create')->name('financeiro.contas.inline');
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
        // Auditoria 2026-04-27 · eventos AGREGADOS (lote inteiro / N de M animais)
        // C2 pesagem amostral/biomassa, C3 mortalidade massa, C4 vacina parcial
        Route::post('rebanho/lotes/{lot}/eventos', [\App\Http\Controllers\Admin\Livestock\AnimalLotEventController::class, 'store'])
            ->middleware('permission:operational.rebanho.eventos.create')
            ->name('rebanho.lotes.eventos.store');
        Route::post('rebanho/animais/vender-lote', [AnimalController::class, 'sellBatch'])->middleware('permission:operational.rebanho.animais.update')->name('rebanho.animais.vender-lote');
        // Evento em LOTE: vacinação/medicação/vermífugo/observação aplicados em múltiplos animais
        Route::post('rebanho/animais/eventos-lote', [AnimalController::class, 'storeEventBatch'])->middleware('permission:operational.rebanho.eventos.create')->name('rebanho.animais.eventos-lote');
        Route::delete('rebanho/animais/{animal}/eventos/{event}', [AnimalController::class, 'destroyEvent'])->middleware('permission:operational.rebanho.eventos.delete')->name('rebanho.animais.eventos.destroy');

        // ──── Correção de domínio · Lotes (grupo) e Locais (pasto/piquete) ────
        // Antes o sistema misturava os dois conceitos em `animal_lots` e nem
        // tinha CRUD. Agora são separados e cada um tem sua própria tela.
        // Endpoint inline (JSON) — criar lote de dentro de outros fluxos sem redirect.
        Route::post('rebanho/lotes/inline', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'storeInline'])->name('rebanho.lotes.inline');
        Route::post('rebanho/locais/inline', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'storeInline'])->name('rebanho.locais.inline');

        Route::get('rebanho/lotes', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'index'])->name('rebanho.lotes.index');
        Route::get('rebanho/lotes/novo', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'create'])->name('rebanho.lotes.create');
        Route::post('rebanho/lotes', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'store'])->name('rebanho.lotes.store');
        Route::get('rebanho/lotes/{lote}/editar', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'edit'])->name('rebanho.lotes.edit');
        Route::put('rebanho/lotes/{lote}', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'update'])->name('rebanho.lotes.update');
        Route::delete('rebanho/lotes/{lote}', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'destroy'])->name('rebanho.lotes.destroy');
        Route::post('rebanho/lotes/{lote}/toggle', [\App\Http\Controllers\Admin\Livestock\AnimalLotController::class, 'toggle'])->name('rebanho.lotes.toggle');

        Route::get('rebanho/locais', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'index'])->name('rebanho.locais.index');
        Route::get('rebanho/locais/novo', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'create'])->name('rebanho.locais.create');
        Route::post('rebanho/locais', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'store'])->name('rebanho.locais.store');
        Route::get('rebanho/locais/{local}/editar', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'edit'])->name('rebanho.locais.edit');
        Route::put('rebanho/locais/{local}', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'update'])->name('rebanho.locais.update');
        Route::delete('rebanho/locais/{local}', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'destroy'])->name('rebanho.locais.destroy');
        Route::post('rebanho/locais/{local}/toggle', [\App\Http\Controllers\Admin\Livestock\AnimalLocationController::class, 'toggle'])->name('rebanho.locais.toggle');

        // F4 · Fluxo guiado (wizard) — Venda de animal
        // FASE 5 · Camada de Inteligência: o wizard agora suporta venda
        // adaptativa (individual, múltiplos, lote, quantidade, peso) com
        // unidades reais de mercado (arroba/kg/litro/unidade/cabeça).
        // O endpoint `store` é unificado e despacha por `modo`.
        Route::get('fluxos/venda-animal', [SaleWizardController::class, 'create'])
            ->middleware('permission:operational.rebanho.eventos.create')
            ->name('fluxos.venda-animal');
        Route::post('fluxos/venda-animal', [SaleWizardController::class, 'store'])
            ->middleware('permission:operational.rebanho.eventos.create')
            ->name('fluxos.venda-animal.store');
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

        // Inline JSON — criar item de estoque sem sair de wizards (Ajustar, Receber, etc.)
        Route::post('estoque/itens/inline', [StockItemController::class, 'storeInline'])
            ->middleware('permission:operational.estoque.itens.create')
            ->name('estoque.itens.inline');
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
