<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $faviconPath = \App\Models\Setting::getValue('site.favicon'); @endphp
    @if($faviconPath)
        <link rel="icon" href="{{ asset('storage/'.$faviconPath) }}?v={{ now()->timestamp }}">
        <link rel="apple-touch-icon" href="{{ asset('storage/'.$faviconPath) }}">
    @else
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/favicon.svg">
    @endif

    <title inertia>{{ config('app.name', 'Fazenda Macaybas') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=playfair-display:700,800&display=swap" rel="stylesheet" />

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased h-full bg-slate-50">
    @inertia
</body>
</html>
