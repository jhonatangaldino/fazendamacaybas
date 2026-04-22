<?php

use App\Http\Controllers\Admin\Cms\CmsController;
use App\Http\Controllers\Admin\Cms\MenuController;
use App\Http\Controllers\Admin\Cms\SettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Financial\FinancialIndexController;
use App\Http\Controllers\Admin\Financial\FinancialTransactionController;
use App\Http\Controllers\Admin\Livestock\AnimalController;
use App\Http\Controllers\Admin\Livestock\LivestockIndexController;
use App\Http\Controllers\Admin\ModuleStubController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// ===================== SITE PÚBLICO =====================
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::post('/newsletter', [SiteController::class, 'newsletter'])->name('site.newsletter');
Route::post('/contato', [SiteController::class, 'contato'])->name('site.contato');
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
});

// ===================== ÁREA ADMIN =====================
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');

    // Usuários
    Route::middleware('permission:users.view')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/novo', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
        Route::post('usuarios/{user}/resetar-senha', [UserController::class, 'resetPassword'])->middleware('permission:users.reset_password')->name('users.reset-password');
    });

    // Perfis (placeholder para listagem)
    Route::get('perfis', [ModuleStubController::class, 'funcionarios'])->middleware('permission:roles.view')->name('roles.index');

    // CMS
    Route::middleware('permission:cms.view')->group(function () {
        Route::get('cms', [CmsController::class, 'index'])->name('cms.index');
        Route::get('cms/pagina/{page}', [CmsController::class, 'edit'])->name('cms.edit');
        Route::put('cms/pagina/{page}', [CmsController::class, 'updatePage'])->middleware('permission:cms.pages.update')->name('cms.update-page');
        Route::put('cms/secao/{section}/rascunho', [CmsController::class, 'saveSectionDraft'])->middleware('permission:cms.update')->name('cms.section.draft');
        Route::post('cms/secao/{section}/publicar', [CmsController::class, 'publishSection'])->middleware('permission:cms.publish')->name('cms.section.publish');
        Route::post('cms/secao/{section}/toggle', [CmsController::class, 'toggleActive'])->middleware('permission:cms.update')->name('cms.section.toggle');
        Route::post('cms/pagina/{page}/publicar-tudo', [CmsController::class, 'publishAll'])->middleware('permission:cms.publish')->name('cms.publish-all');
        Route::post('cms/pagina/{page}/reordenar', [CmsController::class, 'reorderSections'])->middleware('permission:cms.update')->name('cms.reorder');
        Route::post('cms/upload-imagem', [CmsController::class, 'uploadImage'])->middleware('permission:cms.update')->name('cms.upload-image');

        // Menus
        Route::get('cms/menus', [MenuController::class, 'index'])->middleware('permission:cms.menus.view')->name('cms.menus.index');
        Route::post('cms/menus/{menu}/items', [MenuController::class, 'storeItem'])->middleware('permission:cms.menus.create')->name('cms.menus.items.store');
        Route::put('cms/menus/items/{item}', [MenuController::class, 'updateItem'])->middleware('permission:cms.menus.update')->name('cms.menus.items.update');
        Route::delete('cms/menus/items/{item}', [MenuController::class, 'destroyItem'])->middleware('permission:cms.menus.delete')->name('cms.menus.items.destroy');
        Route::post('cms/menus/{menu}/reordenar', [MenuController::class, 'reorder'])->middleware('permission:cms.menus.update')->name('cms.menus.reorder');

        // Settings do site
        Route::get('cms/configuracoes', [SettingsController::class, 'index'])->middleware('permission:cms.settings.view')->name('cms.settings');
        Route::put('cms/configuracoes', [SettingsController::class, 'update'])->middleware('permission:cms.settings.update')->name('cms.settings.update');
        Route::post('cms/configuracoes/upload', [SettingsController::class, 'uploadFile'])->middleware('permission:cms.settings.update')->name('cms.settings.upload');
    });

    // Parceiros
    Route::middleware('permission:parceiros.view')->group(function () {
        Route::get('parceiros', [PartnerController::class, 'index'])->name('parceiros.index');
        Route::get('parceiros/novo', [PartnerController::class, 'create'])->middleware('permission:parceiros.create')->name('parceiros.create');
        Route::post('parceiros', [PartnerController::class, 'store'])->middleware('permission:parceiros.create')->name('parceiros.store');
        Route::get('parceiros/{parceiro}/editar', [PartnerController::class, 'edit'])->middleware('permission:parceiros.update')->name('parceiros.edit');
        Route::put('parceiros/{parceiro}', [PartnerController::class, 'update'])->middleware('permission:parceiros.update')->name('parceiros.update');
        Route::delete('parceiros/{parceiro}', [PartnerController::class, 'destroy'])->middleware('permission:parceiros.delete')->name('parceiros.destroy');
    });

    // Financeiro
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

    // Rebanho
    Route::middleware('permission:rebanho.view')->group(function () {
        Route::get('rebanho', LivestockIndexController::class)->name('rebanho.index');
        Route::get('rebanho/animais', [AnimalController::class, 'index'])->name('rebanho.animais.index');
        Route::get('rebanho/animais/novo', [AnimalController::class, 'create'])->middleware('permission:rebanho.animais.create')->name('rebanho.animais.create');
        Route::post('rebanho/animais', [AnimalController::class, 'store'])->middleware('permission:rebanho.animais.create')->name('rebanho.animais.store');
        Route::get('rebanho/animais/{animal}/editar', [AnimalController::class, 'edit'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.edit');
        Route::put('rebanho/animais/{animal}', [AnimalController::class, 'update'])->middleware('permission:rebanho.animais.update')->name('rebanho.animais.update');
        Route::delete('rebanho/animais/{animal}', [AnimalController::class, 'destroy'])->middleware('permission:rebanho.animais.delete')->name('rebanho.animais.destroy');
    });

    // Módulos com CRUD pendente (stub) — estrutura pronta, UI a expandir
    Route::get('agricola', [ModuleStubController::class, 'agricola'])->middleware('permission:agricola.view')->name('agricola.index');
    Route::get('estoque', [ModuleStubController::class, 'estoque'])->middleware('permission:estoque.view')->name('estoque.index');
    Route::get('maquinas', [ModuleStubController::class, 'maquinas'])->middleware('permission:maquinas.view')->name('maquinas.index');
    Route::get('funcionarios', [ModuleStubController::class, 'funcionarios'])->middleware('permission:funcionarios.view')->name('funcionarios.index');
    Route::get('tarefas', [ModuleStubController::class, 'tarefas'])->middleware('permission:funcionarios.tarefas.view')->name('tarefas.index');
    Route::get('documentos', [ModuleStubController::class, 'documentos'])->middleware('permission:documentos.view')->name('documentos.index');
    Route::get('relatorios', [ModuleStubController::class, 'relatorios'])->middleware('permission:relatorios.view')->name('relatorios.index');
});
