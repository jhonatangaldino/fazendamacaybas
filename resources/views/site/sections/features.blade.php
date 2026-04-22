<section id="areas" class="section-site bg-slate-50">
    <div class="container-site">
        <div class="text-center mb-12">
            @if(!empty($data['subtitulo']))
                <p class="text-sm uppercase tracking-[0.25em] text-macaybas-primary mb-3">{{ $data['subtitulo'] }}</p>
            @endif
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900">{{ $data['titulo'] ?? 'Nossas áreas' }}</h2>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach(($data['items'] ?? []) as $item)
                <div class="bg-white p-8 rounded-2xl shadow-sm ring-1 ring-slate-100 hover:shadow-md transition">
                    <div class="h-12 w-12 rounded-xl bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center mb-4">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ $item['titulo'] ?? '' }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $item['descricao'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
