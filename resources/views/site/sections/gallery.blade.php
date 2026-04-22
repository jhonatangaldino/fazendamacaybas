<section id="galeria" class="section-site bg-white">
    <div class="container-site">
        <div class="text-center mb-12">
            @if(!empty($data['subtitulo']))
                <p class="text-sm uppercase tracking-[0.25em] text-macaybas-primary mb-3">{{ $data['subtitulo'] }}</p>
            @endif
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900">{{ $data['titulo'] ?? 'Galeria' }}</h2>
        </div>

        <div class="grid gap-4 grid-cols-2 md:grid-cols-3" data-lightbox-gallery>
            @foreach(($data['imagens'] ?? []) as $i => $img)
                @php $src = !empty($img['path']) ? asset('storage/'.$img['path']) : null; @endphp
                @if($src)
                    <figure class="group relative aspect-square overflow-hidden rounded-xl bg-slate-200 cursor-zoom-in"
                            data-lightbox-item
                            data-src="{{ $src }}"
                            data-caption="{{ $img['legenda'] ?? '' }}">
                        <img src="{{ $src }}"
                             alt="{{ $img['legenda'] ?? '' }}"
                             loading="lazy"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center">
                            <svg class="h-10 w-10 text-white opacity-0 group-hover:opacity-100 transition drop-shadow-lg"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6M7 10h6"/>
                            </svg>
                        </div>
                        @if(!empty($img['legenda']))
                            <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent text-white text-sm p-3 opacity-0 group-hover:opacity-100 transition">
                                {{ $img['legenda'] }}
                            </figcaption>
                        @endif
                    </figure>
                @else
                    <figure class="relative aspect-square overflow-hidden rounded-xl bg-gradient-to-br from-macaybas-primary-200 to-macaybas-primary-400">
                        @if(!empty($img['legenda']))
                            <figcaption class="absolute inset-x-0 bottom-0 bg-black/50 text-white text-xs p-2">{{ $img['legenda'] }}</figcaption>
                        @endif
                    </figure>
                @endif
            @endforeach
        </div>
    </div>

    {{-- LIGHTBOX --}}
    <div id="lightbox"
         class="fixed inset-0 z-[80] bg-black/90 items-center justify-center hidden opacity-0 transition-opacity duration-200"
         role="dialog"
         aria-modal="true"
         aria-label="Visualização da imagem">
        <button type="button"
                data-lightbox-close
                class="absolute top-4 right-4 sm:top-6 sm:right-6 h-12 w-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white backdrop-blur transition"
                aria-label="Fechar">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <button type="button"
                data-lightbox-prev
                class="absolute left-2 sm:left-6 top-1/2 -translate-y-1/2 h-12 w-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white backdrop-blur transition"
                aria-label="Anterior">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <button type="button"
                data-lightbox-next
                class="absolute right-2 sm:right-6 top-1/2 -translate-y-1/2 h-12 w-12 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white backdrop-blur transition"
                aria-label="Próxima">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <div class="relative max-w-[90vw] max-h-[85vh] flex flex-col items-center justify-center">
            <img data-lightbox-img
                 src=""
                 alt=""
                 class="max-w-[90vw] max-h-[80vh] w-auto h-auto object-contain rounded-lg shadow-2xl">
            <div data-lightbox-caption class="mt-3 text-white text-sm text-center max-w-lg px-4"></div>
            <div data-lightbox-counter class="mt-2 text-white/70 text-xs"></div>
        </div>
    </div>
</section>
