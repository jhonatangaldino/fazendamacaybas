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
    @isset($meta['og_image'])<meta property="og:image" content="{{ $meta['og_image'] }}">@endisset

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/favicon.svg">
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
    @include('site.partials.header')

    <main>
        @yield('content')
    </main>

    @include('site.partials.footer')
</body>
</html>
