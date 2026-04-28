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
{{-- Quando há impersonação: tarja fixa 40px no topo. Marcamos o body
     com data-impersonation pra CSS aplicar offsets nos elementos fixed/sticky
     SEM duplicar padding (que estava criando espaço visual desnecessário).

     IMPORTANTE: a checagem precisa ser IDÊNTICA à da prop Inertia
     `impersonation` em HandleInertiaRequests (is_array && tenant_id não vazio).
     Antes usávamos `session()->has('impersonation')` — true mesmo se a chave
     estivesse com array vazio (lixo de sessão antiga) → body ganhava o
     data-attr mas o banner não renderizava (prop = null) → CSS empurrava
     40px sem banner visível, gerando "espaço fantasma" no topo do master. --}}
@php
    $imp = session()->get('impersonation');
    $hasImpersonation = is_array($imp) && ! empty($imp['tenant_id']);
@endphp
<body class="font-sans antialiased h-full bg-slate-50"
      @if($hasImpersonation) data-impersonation @endif>
    @inertia
</body>
</html>
