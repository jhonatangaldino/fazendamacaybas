<section id="sobre" class="section-site bg-white">
    <div class="container-site grid lg:grid-cols-2 gap-12 items-center">
        <div class="order-2 lg:order-1">
            @if(!empty($data['subtitulo']))
                <p class="text-sm uppercase tracking-[0.25em] text-macaybas-primary mb-3">{{ $data['subtitulo'] }}</p>
            @endif
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900 mb-6">
                {{ $data['titulo'] ?? 'Sobre a fazenda' }}
            </h2>
            @if(!empty($data['texto']))
                <div class="prose prose-slate max-w-none text-slate-600">
                    @foreach(preg_split("/\r\n|\n|\r/", $data['texto']) as $paragrafo)
                        @if(trim($paragrafo) !== '')
                            <p>{{ $paragrafo }}</p>
                        @endif
                    @endforeach
                </div>
            @endif
            @if(!empty($data['cta_texto']) && !empty($data['cta_link']))
                <a href="{{ $data['cta_link'] }}" class="btn-site-primary mt-6">{{ $data['cta_texto'] }}</a>
            @endif
        </div>
        <div class="order-1 lg:order-2">
            <div class="aspect-[4/5] rounded-2xl overflow-hidden bg-macaybas-primary-100 shadow-xl">
                @if(!empty($data['imagem']))
                    <img src="{{ asset('storage/'.$data['imagem']) }}" alt="{{ $data['titulo'] ?? 'Sobre' }}" class="h-full w-full object-cover">
                @else
                    <div class="h-full w-full bg-gradient-to-br from-macaybas-primary-200 to-macaybas-primary-500 flex items-center justify-center">
                        <svg class="h-24 w-24 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/></svg>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
