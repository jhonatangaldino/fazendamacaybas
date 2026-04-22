<section id="fale-conosco" class="section-site bg-white">
    <div class="container-site grid lg:grid-cols-2 gap-12">
        <div>
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900 mb-4">{{ $data['titulo'] ?? 'Fale conosco' }}</h2>
            @if(!empty($data['subtitulo']))
                <p class="text-slate-600 mb-8">{{ $data['subtitulo'] }}</p>
            @endif

            <ul class="space-y-4 text-slate-700">
                @if(!empty($data['email']))
                    <li class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center">✉️</div>
                        <a href="mailto:{{ $data['email'] }}" class="hover:text-macaybas-primary">{{ $data['email'] }}</a>
                    </li>
                @endif
                @if(!empty($data['telefone']))
                    <li class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center">📞</div>
                        <a href="tel:{{ apenasDigitos($data['telefone']) }}" class="hover:text-macaybas-primary">{{ telefoneMask($data['telefone']) }}</a>
                    </li>
                @endif
                @if(!empty($data['endereco']))
                    <li class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center">📍</div>
                        <span>{{ $data['endereco'] }}</span>
                    </li>
                @endif
            </ul>
        </div>

        <form action="{{ route('site.contato') }}" method="POST" class="bg-slate-50 p-8 rounded-2xl space-y-4">
            @csrf
            <div>
                <label class="form-label">Nome</label>
                <input name="nome" required class="form-input">
            </div>
            <div>
                <label class="form-label">E-mail</label>
                <input type="email" name="email" required class="form-input">
            </div>
            <div>
                <label class="form-label">Telefone</label>
                <input name="telefone" class="form-input" placeholder="(31) 99999-9999">
            </div>
            <div>
                <label class="form-label">Mensagem</label>
                <textarea name="mensagem" rows="4" required class="form-textarea"></textarea>
            </div>
            <button type="submit" class="btn-site-primary w-full">Enviar mensagem</button>
        </form>
    </div>
</section>
