@php
    $headerMenu = \App\Models\Cms\Menu::with('rootItems.children')
        ->where('slug', 'header-principal')
        ->where('is_active', true)
        ->first();
    $logoPath = \App\Models\Setting::getValue('site.logo');
    $siteNome = \App\Models\Setting::getValue('site.nome', 'Fazenda Macaybas');
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
            <a href="{{ route('login') }}" class="btn-site-primary">Acessar sistema</a>
        </nav>
    </div>
</header>
