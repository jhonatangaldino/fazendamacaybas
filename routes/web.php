<?php

use App\Http\Controllers\Admin\Agricultural\AgriculturalController;
use App\Http\Controllers\Admin\Cms\CmsController;
use App\Http\Controllers\Admin\Cms\MenuController;
use App\Http\Controllers\Admin\Cms\SettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuUsageController;
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

// ===================== ÁREA ADMIN =====================
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    // Contador de uso dos itens da sidebar — alimenta a ordenação "mais usados no topo"
    Route::post('menu-usage', [MenuUsageController::class, 'bump'])->name('menu-usage.bump');

    // ------- USUÁRIOS -------
    Route::middleware('permission:users.view')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/novo', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
        Route::post('usuarios/{user}/resetar-senha', [UserController::class, 'resetPassword'])->middleware('permission:users.reset_password')->name('users.reset-password');
        Route::post('usuarios/{user}/avatar', [UserController::class, 'uploadAvatar'])->name('users.avatar.upload');
        Route::delete('usuarios/{user}/avatar', [UserController::class, 'removeAvatar'])->name('users.avatar.remove');
    });

    // Perfis e permissões
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('perfis', [RoleController::class, 'index'])->name('roles.index');
        Route::post('perfis', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::put('perfis/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        Route::delete('perfis/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    });

    // ------- CMS (exclusivo Admin Master: ninguém além do master mexe no site público) -------
    Route::middleware(['role:admin_master', 'permission:cms.view'])->group(function () {
        Route::get('cms', [CmsController::class, 'index'])->name('cms.index');
        Route::get('cms/pagina/{page}', [CmsController::class, 'edit'])->name('cms.edit');
        Route::put('cms/pagina/{page}', [CmsController::class, 'updatePage'])->middleware('permission:cms.pages.update')->name('cms.update-page');
        Route::put('cms/secao/{section}/rascunho', [CmsController::class, 'saveSectionDraft'])->middleware('permission:cms.update')->name('cms.section.draft');
        Route::post('cms/secao/{section}/publicar', [CmsController::class, 'publishSection'])->middleware('permission:cms.publish')->name('cms.section.publish');
        Route::post('cms/secao/{section}/toggle', [CmsController::class, 'toggleActive'])->middleware('permission:cms.update')->name('cms.section.toggle');
        Route::post('cms/pagina/{page}/publicar-tudo', [CmsController::class, 'publishAll'])->middleware('permission:cms.publish')->name('cms.publish-all');
        Route::post('cms/pagina/{page}/reordenar', [CmsController::class, 'reorderSections'])->middleware('permission:cms.update')->name('cms.reorder');
        Route::post('cms/upload-imagem', [CmsController::class, 'uploadImage'])->middleware('permission:cms.update')->name('cms.upload-image');

        Route::get('cms/menus', [MenuController::class, 'index'])->middleware('permission:cms.menus.view')->name('cms.menus.index');
        Route::post('cms/menus/{menu}/items', [MenuController::class, 'storeItem'])->middleware('permission:cms.menus.create')->name('cms.menus.items.store');
        Route::put('cms/menus/items/{item}', [MenuController::class, 'updateItem'])->middleware('permission:cms.menus.update')->name('cms.menus.items.update');
        Route::delete('cms/menus/items/{item}', [MenuController::class, 'destroyItem'])->middleware('permission:cms.menus.delete')->name('cms.menus.items.destroy');
        Route::post('cms/menus/{menu}/reordenar', [MenuController::class, 'reorder'])->middleware('permission:cms.menus.update')->name('cms.menus.reorder');

        Route::get('cms/configuracoes', [SettingsController::class, 'index'])->middleware('permission:cms.settings.view')->name('cms.settings');
        Route::put('cms/configuracoes', [SettingsController::class, 'update'])->middleware('permission:cms.settings.update')->name('cms.settings.update');
        Route::post('cms/configuracoes/upload', [SettingsController::class, 'uploadFile'])->middleware('permission:cms.settings.update')->name('cms.settings.upload');
        Route::post('cms/configuracoes/remover-arquivo', [SettingsController::class, 'removeFile'])->middleware('permission:cms.settings.update')->name('cms.settings.remove-file');
    });

    // ------- PARCEIROS -------
    Route::middleware('permission:parceiros.view')->group(function () {
        Route::get('parceiros', [PartnerController::class, 'index'])->name('parceiros.index');
        Route::get('parceiros/novo', [PartnerController::class, 'create'])->middleware('permission:parceiros.create')->name('parceiros.create');
        Route::post('parceiros', [PartnerController::class, 'store'])->middleware('permission:parceiros.create')->name('parceiros.store');
        Route::get('parceiros/{parceiro}/editar', [PartnerController::class, 'edit'])->middleware('permission:parceiros.update')->name('parceiros.edit');
        Route::put('parceiros/{parceiro}', [PartnerController::class, 'update'])->middleware('permission:parceiros.update')->name('parceiros.update');
        Route::delete('parceiros/{parceiro}', [PartnerController::class, 'destroy'])->middleware('permission:parceiros.delete')->name('parceiros.destroy');
    });

    // ------- FINANCEIRO -------
    Route::middleware('permission:financeiro.view')->group(function () {
        Route::get('financeiro', FinancialIndexController::class)->name('financeiro.index');
        Route::get('financeiro/transacoes', [FinancialTransactionController::class, 'index'])->name('financeiro.transacoes.index');
        Route::get('financeiro/transacoes/novo', [FinancialTransactionController::class, 'create'])->middleware('permission:financeiro.transacoes.create')->name('financeiro.transacoes.create');
        Route::post('financeiro/transacoes', [FinancialTransactionController::class, 'store'])->middleware('permission:financeiro.transacoes.create')->name('financeiro.transacoes.store');
        Route::get('financeiro/transacoes/{transacao}/editar', [FinancialTransactionController::class, 'edit'])->middleware('permission:financeiro.transacoes.update')->name('financeiro.transacoes.edit');
        Route::put('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'update'])->middleware('permission:financeiro.transacoes.update')->name('financeiro.transacoes.update');
        Route::delete('financeiro/transacoes/{transacao}', [FinancialTransactionController::class, 'destroy'])->middleware('permission:financeiro.transacoes.delete')->name('financeiro.transacoes.destroy');
        Route::post('financeiro/transacoes/{transacao}/pagar', [FinancialTransactionController::class, 'pay'])->middleware('permission:financeiro.transacoes.update')->name('financeiro.transacoes.pay');
    });

    // ------- REBANHO -------
    Route::middleware('permission:rebanho.view')->group(function () {
        Route::get('rebanho', LivestockIndexController::class)->name('rebanho.index');
        Route::get('rebanho/animais', [AnimalController::class, 'index'])->name('rebanho.animais.index');
        Route::get('rebanho/animais/novo', [AnimalController::class, 'create'])->middleware('permission:rebanho.animais.create')->name('rebanho.animais.create');
        Route::post('rebanho/animais', [AnimalController::class, 'store'])->middleware('permission:rebanho.animais.create')->name('rebanho.animais.store');
        Route::get('rebanho/animais/{animal}/editar', [AnimalController::class, 'edit'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.edit');
        Route::put('rebanho/animais/{animal}', [AnimalController::class, 'update'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.update');
        Route::delete('rebanho/animais/{animal}', [AnimalController::class, 'destroy'])->middleware('permission:rebanho.animais.delete')->name('rebanho.animais.destroy');
        Route::post('rebanho/animais/{animal}/foto', [AnimalController::class, 'uploadPhoto'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.foto.upload');
        Route::delete('rebanho/animais/{animal}/foto', [AnimalController::class, 'removePhoto'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.foto.remove');
    });

    // ------- AGRÍCOLA -------
    Route::middleware('permission:agricola.view')->group(function () {
        Route::get('agricola', [AgriculturalController::class, 'index'])->name('agricola.index');

        Route::get('agricola/talhoes', [AgriculturalController::class, 'fields'])->name('agricola.talhoes.index');
        Route::post('agricola/talhoes', [AgriculturalController::class, 'fieldStore'])->middleware('permission:agricola.talhoes.create')->name('agricola.talhoes.store');
        Route::put('agricola/talhoes/{field}', [AgriculturalController::class, 'fieldUpdate'])->middleware('permission:agricola.talhoes.update')->name('agricola.talhoes.update');
        Route::delete('agricola/talhoes/{field}', [AgriculturalController::class, 'fieldDestroy'])->middleware('permission:agricola.talhoes.delete')->name('agricola.talhoes.destroy');
        Route::post('agricola/talhoes/{field}/toggle', [AgriculturalController::class, 'fieldToggle'])->middleware('permission:agricola.talhoes.update')->name('agricola.talhoes.toggle');

        Route::get('agricola/culturas', [AgriculturalController::class, 'crops'])->name('agricola.culturas.index');
        Route::post('agricola/culturas', [AgriculturalController::class, 'cropStore'])->name('agricola.culturas.store');
        Route::delete('agricola/culturas/{crop}', [AgriculturalController::class, 'cropDestroy'])->name('agricola.culturas.destroy');
        Route::post('agricola/safras', [AgriculturalController::class, 'seasonStore'])->name('agricola.safras.store');
        Route::delete('agricola/safras/{season}', [AgriculturalController::class, 'seasonDestroy'])->name('agricola.safras.destroy');

        Route::get('agricola/plantios', [AgriculturalController::class, 'plantings'])->name('agricola.plantios.index');
        Route::post('agricola/plantios', [AgriculturalController::class, 'plantingStore'])->middleware('permission:agricola.plantios.create')->name('agricola.plantios.store');
        Route::put('agricola/plantios/{planting}', [AgriculturalController::class, 'plantingUpdate'])->middleware('permission:agricola.plantios.update')->name('agricola.plantios.update');
        Route::delete('agricola/plantios/{planting}', [AgriculturalController::class, 'plantingDestroy'])->middleware('permission:agricola.plantios.delete')->name('agricola.plantios.destroy');

        Route::get('agricola/colheitas', [AgriculturalController::class, 'harvests'])->name('agricola.colheitas.index');
        Route::post('agricola/colheitas', [AgriculturalController::class, 'harvestStore'])->middleware('permission:agricola.colheitas.create')->name('agricola.colheitas.store');
        Route::delete('agricola/colheitas/{harvest}', [AgriculturalController::class, 'harvestDestroy'])->middleware('permission:agricola.colheitas.delete')->name('agricola.colheitas.destroy');

        Route::get('agricola/aplicacoes', [AgriculturalController::class, 'applications'])->name('agricola.aplicacoes.index');
        Route::post('agricola/aplicacoes', [AgriculturalController::class, 'applicationStore'])->middleware('permission:agricola.aplicacoes.create')->name('agricola.aplicacoes.store');
        Route::delete('agricola/aplicacoes/{application}', [AgriculturalController::class, 'applicationDestroy'])->middleware('permission:agricola.aplicacoes.delete')->name('agricola.aplicacoes.destroy');
    });

    // ------- ESTOQUE -------
    Route::middleware('permission:estoque.view')->group(function () {
        Route::get('estoque', fn () => Inertia::render('Admin/Stock/Index'))->name('estoque.index');

        Route::get('estoque/itens', [StockItemController::class, 'index'])->name('estoque.itens.index');
        Route::get('estoque/itens/novo', [StockItemController::class, 'create'])->middleware('permission:estoque.itens.create')->name('estoque.itens.create');
        Route::post('estoque/itens', [StockItemController::class, 'store'])->middleware('permission:estoque.itens.create')->name('estoque.itens.store');
        Route::get('estoque/itens/{item}/editar', [StockItemController::class, 'edit'])->middleware('permission:estoque.itens.update')->name('estoque.itens.edit');
        Route::put('estoque/itens/{item}', [StockItemController::class, 'update'])->middleware('permission:estoque.itens.update')->name('estoque.itens.update');
        Route::delete('estoque/itens/{item}', [StockItemController::class, 'destroy'])->middleware('permission:estoque.itens.delete')->name('estoque.itens.destroy');
        Route::post('estoque/itens/{item}/toggle', [StockItemController::class, 'toggle'])->middleware('permission:estoque.itens.update')->name('estoque.itens.toggle');

        Route::get('estoque/movimentos', [StockMovementController::class, 'index'])->name('estoque.movimentos.index');
        Route::post('estoque/movimentos', [StockMovementController::class, 'store'])->middleware('permission:estoque.movimentos.create')->name('estoque.movimentos.store');
        Route::delete('estoque/movimentos/{movement}', [StockMovementController::class, 'destroy'])->middleware('permission:estoque.movimentos.delete')->name('estoque.movimentos.destroy');

        Route::get('estoque/armazens', [WarehouseController::class, 'index'])->name('estoque.armazens.index');
        Route::post('estoque/armazens', [WarehouseController::class, 'store'])->middleware('permission:estoque.armazens.create')->name('estoque.armazens.store');
        Route::put('estoque/armazens/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:estoque.armazens.update')->name('estoque.armazens.update');
        Route::delete('estoque/armazens/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:estoque.armazens.delete')->name('estoque.armazens.destroy');
        Route::post('estoque/armazens/{warehouse}/toggle', [WarehouseController::class, 'toggle'])->middleware('permission:estoque.armazens.update')->name('estoque.armazens.toggle');
    });

    // ------- MÁQUINAS -------
    Route::middleware('permission:maquinas.view')->group(function () {
        Route::get('maquinas', [VehicleController::class, 'index'])->name('maquinas.index');

        Route::get('maquinas/veiculos', [VehicleController::class, 'vehicles'])->name('maquinas.veiculos.index');
        Route::post('maquinas/veiculos', [VehicleController::class, 'vehicleStore'])->middleware('permission:maquinas.veiculos.create')->name('maquinas.veiculos.store');
        Route::put('maquinas/veiculos/{vehicle}', [VehicleController::class, 'vehicleUpdate'])->middleware('permission:maquinas.veiculos.update')->name('maquinas.veiculos.update');
        Route::delete('maquinas/veiculos/{vehicle}', [VehicleController::class, 'vehicleDestroy'])->middleware('permission:maquinas.veiculos.delete')->name('maquinas.veiculos.destroy');
        Route::post('maquinas/veiculos/{vehicle}/toggle', [VehicleController::class, 'vehicleToggle'])->middleware('permission:maquinas.veiculos.update')->name('maquinas.veiculos.toggle');

        Route::get('maquinas/manutencoes', [VehicleController::class, 'maintenance'])->name('maquinas.manutencoes.index');
        Route::post('maquinas/manutencoes', [VehicleController::class, 'maintenanceStore'])->middleware('permission:maquinas.manutencoes.create')->name('maquinas.manutencoes.store');
        Route::put('maquinas/manutencoes/{order}', [VehicleController::class, 'maintenanceUpdate'])->middleware('permission:maquinas.manutencoes.update')->name('maquinas.manutencoes.update');
        Route::delete('maquinas/manutencoes/{order}', [VehicleController::class, 'maintenanceDestroy'])->middleware('permission:maquinas.manutencoes.delete')->name('maquinas.manutencoes.destroy');
    });

    // ------- FUNCIONÁRIOS -------
    Route::middleware('permission:funcionarios.view')->group(function () {
        Route::get('funcionarios', [EmployeeController::class, 'index'])->name('funcionarios.index');
        Route::post('funcionarios', [EmployeeController::class, 'store'])->middleware('permission:funcionarios.cadastro.create')->name('funcionarios.store');
        Route::put('funcionarios/{employee}', [EmployeeController::class, 'update'])->middleware('permission:funcionarios.cadastro.update')->name('funcionarios.update');
        Route::delete('funcionarios/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:funcionarios.cadastro.delete')->name('funcionarios.destroy');
        Route::post('funcionarios/{employee}/toggle', [EmployeeController::class, 'toggle'])->middleware('permission:funcionarios.cadastro.update')->name('funcionarios.toggle');
    });

    // ------- TAREFAS -------
    Route::middleware('permission:funcionarios.tarefas.view')->group(function () {
        Route::get('tarefas', [TaskController::class, 'index'])->name('tarefas.index');
        Route::post('tarefas', [TaskController::class, 'store'])->middleware('permission:funcionarios.tarefas.create')->name('tarefas.store');
        Route::put('tarefas/{task}', [TaskController::class, 'update'])->middleware('permission:funcionarios.tarefas.update')->name('tarefas.update');
        Route::delete('tarefas/{task}', [TaskController::class, 'destroy'])->middleware('permission:funcionarios.tarefas.delete')->name('tarefas.destroy');
        Route::post('tarefas/{task}/concluir', [TaskController::class, 'complete'])->middleware('permission:funcionarios.tarefas.update')->name('tarefas.complete');
        Route::post('tarefas/{task}/reabrir', [TaskController::class, 'reopen'])->middleware('permission:funcionarios.tarefas.update')->name('tarefas.reopen');
        Route::post('tarefas/checklist/{item}/toggle', [TaskController::class, 'toggleChecklistItem'])->middleware('permission:funcionarios.tarefas.update')->name('tarefas.checklist.toggle');
    });

    // ------- DOCUMENTOS -------
    Route::middleware('permission:documentos.view')->group(function () {
        Route::get('documentos', [DocumentController::class, 'index'])->name('documentos.index');
        Route::post('documentos', [DocumentController::class, 'store'])->middleware('permission:documentos.create')->name('documentos.store');
        Route::delete('documentos/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documentos.delete')->name('documentos.destroy');
        Route::post('documentos/categorias', [DocumentController::class, 'categoryStore'])->middleware('permission:documentos.create')->name('documentos.categorias.store');
        Route::delete('documentos/categorias/{category}', [DocumentController::class, 'categoryDestroy'])->middleware('permission:documentos.delete')->name('documentos.categorias.destroy');
    });

    // ------- RELATÓRIOS -------
    Route::get('relatorios', [ReportController::class, 'index'])
        ->middleware('permission:relatorios.view')->name('relatorios.index');
});
