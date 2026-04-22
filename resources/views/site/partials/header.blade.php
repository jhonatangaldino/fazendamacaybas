@php
    $headerMenu = \App\Models\Cms\Menu::with('rootItems.children')
        ->where('slug', 'header-principal')
        ->where('is_active', true)
        ->first();
    $logoPath = \App\Models\Setting::getValue('site.logo');
    $siteNome = \App\Models\Setting::getValue('site.nome', 'Fazenda Macaybas');
    $authUser = auth()->user();
@endphp

<header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200">
    <div class="container-site flex items-center justify-between py-4">
        <a href="/" class="flex items-center gap-3">
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

        <button data-menu-toggle class="lg:hidden p-2 rounded-md hover:bg-slate-100" aria-label="Abrir menu">
            <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <nav data-menu class="hidden lg:flex items-center gap-8 absolute lg:static top-full left-0 right-0 bg-white lg:bg-transparent border-b lg:border-0 border-slate-200 p-6 lg:p-0 flex-col lg:flex-row">
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
                {{-- Usuário logado: menu com avatar --}}
                <div class="relative lg:ml-4" x-data="{ open: false }">
                    <button type="button"
                            onclick="this.parentElement.querySelector('[data-user-menu]').classList.toggle('hidden')"
                            class="flex items-center gap-3 rounded-full bg-macaybas-primary-50 hover:bg-macaybas-primary-100 transition px-3 py-1.5 border border-macaybas-primary-200 w-full lg:w-auto justify-between lg:justify-start">
                        @if($authUser->avatarUrl())
                            <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->name }}"
                                 class="h-9 w-9 rounded-full object-cover ring-1 ring-white">
                        @else
                            <div class="h-9 w-9 rounded-full bg-macaybas-primary text-white flex items-center justify-center text-sm font-semibold">
                                {{ strtoupper(substr($authUser->name, 0, 1)) }}
                            </div>
                        @endif
                        <div class="text-left min-w-0">
                            <div class="text-xs text-slate-500 leading-tight">Olá,</div>
                            <div class="text-sm font-semibold text-macaybas-primary-900 leading-tight truncate max-w-[140px]">
                                {{ explode(' ', trim($authUser->name))[0] }}
                            </div>
                        </div>
                        <svg class="h-4 w-4 text-slate-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Acessar painel
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 mt-1 pt-1">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            @else
                {{-- Não logado --}}
                <a href="{{ route('login') }}" class="btn-site-primary">Acessar sistema</a>
            @endif
        </nav>
    </div>
</header>
