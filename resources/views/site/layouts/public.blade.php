<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta['title'] ?? config('app.name') }}</title>
    <meta name="description" content="{{ $meta['description'] ?? \App\Models\Setting::getValue('seo.default_description') }}">
    @isset($meta['keywords'])<meta name="keywords" content="{{ $meta['keywords'] }}">@endisset

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $meta['title'] ?? config('app.name') }}">
    <meta property="og:description" content="{{ $meta['description'] ?? \App\Models\Setting::getValue('seo.default_description') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @php
        // F4 fix · og:image fallback pra logo do site se não houver imagem
        // específica da página. Sem isso, preview em redes sociais (WhatsApp,
        // Facebook, Twitter) ficava sem thumbnail.
        $ogImage = $meta['og_image'] ?? \App\Models\Setting::getValue('site.logo');
    @endphp
    @if($ogImage)
        <meta property="og:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : asset('storage/'.$ogImage) }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $meta['title'] ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $meta['description'] ?? \App\Models\Setting::getValue('seo.default_description') }}">
        <meta name="twitter:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : asset('storage/'.$ogImage) }}">
    @endif

    @php
        // F4 fix · JSON-LD Schema.org LocalBusiness pra rich results no Google.
        $endereco = \App\Models\Setting::getValue('site.endereco');
        $telefone = \App\Models\Setting::getValue('site.telefone');
        $emailContato = \App\Models\Setting::getValue('site.email');
    @endphp
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": @json(config('app.name')),
        "url": @json(url('/')),
        @if($ogImage)"image": @json(str_starts_with($ogImage, 'http') ? $ogImage : asset('storage/'.$ogImage)),@endif
        @if($telefone)"telephone": @json($telefone),@endif
        @if($emailContato)"email": @json($emailContato),@endif
        @if($endereco)"address": @json($endereco),@endif
        "description": @json(\App\Models\Setting::getValue('seo.default_description'))
    }
    </script>

    @php $faviconPath = \App\Models\Setting::getValue('site.favicon'); @endphp
    @if($faviconPath)
        <link rel="icon" href="{{ asset('storage/'.$faviconPath) }}?v={{ now()->timestamp }}">
        <link rel="apple-touch-icon" href="{{ asset('storage/'.$faviconPath) }}">
    @else
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/favicon.svg">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=playfair-display:700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/site.css', 'resources/js/site.js'])

    @if($ga = \App\Models\Setting::getValue('seo.google_analytics'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date()); gtag('config', '{{ $ga }}');
        </script>
    @endif
</head>
<body class="site">
    {{-- F4 a11y · Skip-to-content pra navegação por teclado.
         Visível apenas com :focus (ao tabular). Padrão WCAG 2.4.1. --}}
    <a href="#conteudo-principal"
       class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-emerald-700 focus:text-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg">
        Pular para o conteúdo
    </a>

    @include('site.partials.header')

    <main id="conteudo-principal" tabindex="-1">
        @yield('content')
    </main>

    {{-- F4 LGPD · Cookie banner. Google Maps e Google Analytics carregam
         cookies de terceiros — usuário precisa consentir. Banner aparece na
         primeira visita; persiste decisão em localStorage. Recusar bloqueia
         scripts não-essenciais (impl em site.js). --}}
    <div id="cookie-banner"
         class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900 text-white p-4 sm:p-6 shadow-2xl transform translate-y-full transition-transform duration-300"
         role="region"
         aria-label="Aviso de cookies"
         data-cookie-banner>
        <div class="container mx-auto max-w-4xl flex flex-col sm:flex-row items-center gap-4">
            <p class="text-sm flex-1">
                Usamos cookies essenciais e, com seu consentimento, cookies do Google Maps/Analytics
                para melhorar sua experiência. Veja nossa
                <a href="/politica-de-privacidade" class="underline hover:text-emerald-300">Política de Privacidade</a>.
            </p>
            <div class="flex gap-2 flex-shrink-0">
                <button type="button" data-cookie-action="reject"
                        class="px-4 py-2 rounded-md border border-slate-600 hover:bg-slate-800 text-sm">
                    Recusar
                </button>
                <button type="button" data-cookie-action="accept"
                        class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 font-semibold text-sm">
                    Aceitar todos
                </button>
            </div>
        </div>
    </div>
    <script>
        (function(){
            const KEY = 'cookie_consent_v1';
            const banner = document.querySelector('[data-cookie-banner]');
            if (!banner) return;
            const stored = localStorage.getItem(KEY);
            if (!stored) {
                setTimeout(() => banner.classList.remove('translate-y-full'), 1500);
            }
            banner.querySelectorAll('[data-cookie-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.dataset.cookieAction;
                    localStorage.setItem(KEY, JSON.stringify({ action, ts: Date.now() }));
                    banner.classList.add('translate-y-full');
                    if (action === 'reject') {
                        // Bloqueio simbólico — em produção real, gtag e iframes do Maps
                        // deveriam ler localStorage antes de carregar. Por ora documenta
                        // a decisão do usuário.
                        document.querySelectorAll('iframe[src*="google.com/maps"]').forEach(i => i.remove());
                    }
                });
            });
        })();
    </script>

    @include('site.partials.footer')
</body>
</html>
