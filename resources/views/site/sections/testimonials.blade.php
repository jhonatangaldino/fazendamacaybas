<section id="depoimentos" class="section-site bg-slate-50">
    <div class="container-site">
        <div class="text-center mb-12">
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900">{{ $data['titulo'] ?? 'Depoimentos' }}</h2>
            @if(!empty($data['subtitulo']))
                <p class="mt-2 text-slate-600">{{ $data['subtitulo'] }}</p>
            @endif
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach(($data['items'] ?? []) as $t)
                <div class="bg-white p-8 rounded-2xl shadow-sm ring-1 ring-slate-100">
                    <svg class="h-8 w-8 text-macaybas-secondary mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z"/></svg>
                    <p class="text-slate-700 italic mb-4">"{{ $t['texto'] ?? '' }}"</p>
                    <div class="flex items-center gap-3">
                        @if(!empty($t['foto']))
                            <img src="{{ asset('storage/'.$t['foto']) }}" alt="{{ $t['nome'] ?? '' }}" class="h-12 w-12 rounded-full object-cover">
                        @else
                            <div class="h-12 w-12 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center font-semibold">
                                {{ strtoupper(substr($t['nome'] ?? '?', 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="font-semibold text-slate-900">{{ $t['nome'] ?? '' }}</div>
                            @if(!empty($t['cargo']))<div class="text-xs text-slate-500">{{ $t['cargo'] }}</div>@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
