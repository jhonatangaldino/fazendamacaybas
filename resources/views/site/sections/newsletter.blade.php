<section id="newsletter" class="section-site bg-macaybas-primary text-white">
    <div class="container-site max-w-3xl text-center">
        <h2 class="font-serif text-3xl sm:text-4xl font-bold mb-4">{{ $data['titulo'] ?? 'Receba novidades' }}</h2>
        @if(!empty($data['subtitulo']))
            <p class="text-slate-100/90 mb-8">{{ $data['subtitulo'] }}</p>
        @endif

        <form data-newsletter action="{{ route('site.newsletter') }}" method="POST" class="flex flex-col sm:flex-row gap-3 max-w-xl mx-auto">
            @csrf
            <input type="email" name="email" required
                   placeholder="{{ $data['placeholder'] ?? 'Seu melhor e-mail' }}"
                   class="flex-1 rounded-full px-5 py-3 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-macaybas-secondary">
            <button type="submit" class="rounded-full bg-macaybas-secondary hover:bg-macaybas-secondary-700 px-8 py-3 font-semibold transition">
                {{ $data['cta_texto'] ?? 'Quero receber' }}
            </button>
        </form>
        <p data-newsletter-status class="text-sm mt-3"></p>
    </div>
</section>
