<section id="galeria" class="section-site bg-white">
    <div class="container-site">
        <div class="text-center mb-12">
            @if(!empty($data['subtitulo']))
                <p class="text-sm uppercase tracking-[0.25em] text-macaybas-primary mb-3">{{ $data['subtitulo'] }}</p>
            @endif
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900">{{ $data['titulo'] ?? 'Galeria' }}</h2>
        </div>

        <div class="grid gap-4 grid-cols-2 md:grid-cols-3">
            @foreach(($data['imagens'] ?? []) as $img)
                <figure class="group relative aspect-square overflow-hidden rounded-xl bg-slate-200">
                    @if(!empty($img['path']))
                        <img src="{{ asset('storage/'.$img['path']) }}"
                             alt="{{ $img['legenda'] ?? '' }}"
                             loading="lazy"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="h-full w-full bg-gradient-to-br from-macaybas-primary-200 to-macaybas-primary-400"></div>
                    @endif
                    @if(!empty($img['legenda']))
                        <figcaption class="absolute inset-x-0 bottom-0 bg-black/50 text-white text-xs p-2 opacity-0 group-hover:opacity-100 transition">
                            {{ $img['legenda'] }}
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</section>
