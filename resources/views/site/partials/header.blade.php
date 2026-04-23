@php
    // Filtra o menu pelo tenant ativo — SiteController binda `app('tenant_id')`
    // antes de renderizar (tenant 1 por default em "/", override em "/c/{slug}").
    $tenantId = app()->bound('tenant_id') ? (int) app('tenant_id') : 1;
    $headerMenu = \App\Models\Cms\Menu::with('rootItems.children')
        ->where('tenant_id', $tenantId)
        ->where('slug', 'header-principal')
        ->where('is_active', true)
        ->first();
    $logoPath = \App\Models\Setting::getValue('site.logo');
    $siteNome = \App\Models\Setting::getValue('site.nome', 'Fazenda Macaybas');
    $authUser = auth()->user();
@endphp

<header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200">
    <div class="container-site flex items-center justify-between py-4">
        {{-- LOGO --}}
        <a href="/" class="flex items-center gap-3 flex-shrink-0">
            @if($logoPath)
                <img src="{{ asset('storage/'.$logoPath) }}" alt="{{ $siteNome }}" class="h-10 w-auto">
            @else
                <div class="flex items-center gap-2">
                    <div class="h-10 w-10 rounded-full bg-macaybas-primary text-white flex items-center justify-center font-serif text-xl font-bold">M</div>
                    <div class="hidden sm:block">
                        <div class="font-serif text-lg font-bold text-macaybas-primary-900 leading-none">{{ $siteNome }}</div>
                        <div class="text-xs text-slate-500">Itabirito — MG</div>
                    </div>
                </div>
            @endif
        </a>

        {{-- MENU DESKTOP (>= lg) --}}
        <nav class="hidden lg:flex items-center gap-8">
            @if($headerMenu)
                @foreach($headerMenu->rootItems as $item)
                    @if($item->is_active)
                        <a href="{{ $item->url }}" target="{{ $item->target }}"
                           class="text-sm font-medium text-slate-700 hover:text-macaybas-primary transition-colors">
                            {{ $item->label }}
                        </a>
                    @endif
                @endforeach
            @endif

            @if($authUser)
                <div class="relative" data-user-dropdown>
                    <button type="button" data-user-trigger
                            class="flex items-center gap-3 rounded-full bg-macaybas-primary-50 hover:bg-macaybas-primary-100 transition px-3 py-1.5 border border-macaybas-primary-200">
                        @if($authUser->avatarUrl())
                            <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->name }}"
                                 class="h-8 w-8 rounded-full object-cover ring-1 ring-white">
                        @else
                            <div class="h-8 w-8 rounded-full bg-macaybas-primary text-white flex items-center justify-center text-sm font-semibold">
                                {{ strtoupper(substr($authUser->name, 0, 1)) }}
                            </div>
                        @endif
                        <span class="text-sm font-semibold text-macaybas-primary-900">
                            {{ explode(' ', trim($authUser->name))[0] }}
                        </span>
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div data-user-menu
                         class="hidden absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-lg ring-1 ring-slate-200 py-2 z-50">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <div class="text-sm font-semibold text-slate-900">{{ $authUser->name }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ $authUser->email }}</div>
                            @if($authUser->cargo)
                                <div class="text-xs text-macaybas-primary font-medium mt-0.5">{{ $authUser->cargo }}</div>
                            @endif
                        </div>
                        <a href="/admin/dashboard"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                            </svg>
                            Acessar painel
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 mt-1 pt-1">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-site-primary">Acessar sistema</a>
            @endif
        </nav>

        {{-- BOTÃO HAMBÚRGUER MOBILE (< lg) --}}
        <button type="button" data-menu-open
                class="lg:hidden h-11 w-11 flex items-center justify-center rounded-lg hover:bg-slate-100"
                aria-label="Abrir menu">
            <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>
</header>

{{-- =========== DRAWER MOBILE =========== --}}
<div data-mobile-overlay
     class="fixed inset-0 z-40 bg-black/50 lg:hidden hidden opacity-0 transition-opacity duration-200"
     aria-hidden="true"></div>

<aside data-mobile-drawer
       class="fixed inset-y-0 right-0 z-50 w-[85%] max-w-sm bg-white shadow-2xl lg:hidden transform translate-x-full transition-transform duration-300 flex flex-col"
       role="dialog"
       aria-modal="true"
       aria-label="Menu">
    <div class="flex items-center justify-between p-5 border-b border-slate-200">
        <div class="flex items-center gap-2 min-w-0">
            @if($logoPath)
                <img src="{{ asset('storage/'.$logoPath) }}" alt="{{ $siteNome }}" class="h-9 w-auto flex-shrink-0">
            @else
                <div class="h-9 w-9 rounded-full bg-macaybas-primary text-white flex items-center justify-center font-serif font-bold flex-shrink-0">M</div>
            @endif
            <span class="font-serif font-bold text-macaybas-primary-900 truncate">{{ $siteNome }}</span>
        </div>
        <button type="button" data-menu-close
                class="h-10 w-10 flex items-center justify-center rounded-full hover:bg-slate-100 flex-shrink-0"
                aria-label="Fechar menu">
            <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Card do usuário logado --}}
    @if($authUser)
        <div class="p-5 border-b border-slate-200 bg-macaybas-primary-50">
            <div class="flex items-center gap-3">
                @if($authUser->avatarUrl())
                    <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->name }}"
                         class="h-12 w-12 rounded-full object-cover ring-2 ring-white">
                @else
                    <div class="h-12 w-12 rounded-full bg-macaybas-primary text-white flex items-center justify-center text-lg font-semibold">
                        {{ strtoupper(substr($authUser->name, 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-900 truncate">{{ $authUser->name }}</div>
                    <div class="text-xs text-slate-500 truncate">{{ $authUser->email }}</div>
                    @if($authUser->cargo)
                        <div class="text-xs text-macaybas-primary font-medium">{{ $authUser->cargo }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Links do menu --}}
    <nav class="flex-1 overflow-y-auto py-2">
        @if($headerMenu)
            @foreach($headerMenu->rootItems as $item)
                @if($item->is_active)
                    <a href="{{ $item->url }}" target="{{ $item->target }}"
                       data-menu-link
                       class="block px-5 py-3 text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-macaybas-primary transition border-l-4 border-transparent hover:border-macaybas-primary">
                        {{ $item->label }}
                    </a>
                @endif
            @endforeach
        @endif
    </nav>

    {{-- Ações de rodapé --}}
    <div class="p-5 border-t border-slate-200 space-y-2">
        @if($authUser)
            <a href="/admin/dashboard" class="btn-site-primary w-full justify-center">Acessar painel</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-full border border-red-200 text-red-600 font-medium hover:bg-red-50 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sair
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn-site-primary w-full justify-center">Acessar sistema</a>
        @endif
    </div>
</aside>
