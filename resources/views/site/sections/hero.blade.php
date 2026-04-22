@php
    $bg = !empty($data['imagem_fundo']) ? asset('storage/'.$data['imagem_fundo']) : null;
    $overlay = $data['overlay_opacity'] ?? 0.45;
@endphp
<section id="hero" class="relative isolate overflow-hidden bg-macaybas-primary-950 text-white min-h-[70vh] flex items-center">
    @if($bg)
        <img src="{{ $bg }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-macaybas-primary-900 via-macaybas-primary-800 to-macaybas-accent-800"></div>
    @endif
    <div class="absolute inset-0" style="background: rgba(0,0,0,{{ $overlay }});"></div>

    <div class="container-site relative py-24 sm:py-32 text-center">
        @if(!empty($data['eyebrow']))
            <p class="text-sm uppercase tracking-[0.3em] text-macaybas-secondary-300 mb-4">{{ $data['eyebrow'] }}</p>
        @endif
        <h1 class="font-serif text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight mb-6">
            {{ $data['titulo'] ?? 'Fazenda Macaybas' }}
        </h1>
        @if(!empty($data['subtitulo']))
            <p class="max-w-2xl mx-auto text-lg sm:text-xl text-slate-100/90 mb-8">{{ $data['subtitulo'] }}</p>
        @endif
        @if(!empty($data['cta_texto']) && !empty($data['cta_link']))
            <a href="{{ $data['cta_link'] }}" class="btn-site-primary">
                {{ $data['cta_texto'] }}
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            </a>
        @endif
    </div>
</section>
