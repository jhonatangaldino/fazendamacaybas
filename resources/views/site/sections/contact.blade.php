@php
    // Endereço e contatos vêm da ÚNICA fonte: Configurações do site → Contato.
    // O bloco "Onde estamos" controla apenas título e subtítulo.
    $endereco = \App\Models\Setting::getValue('contato.endereco', 'Itabirito, Minas Gerais');
    $email = \App\Models\Setting::getValue('contato.email');
    $telefone = \App\Models\Setting::getValue('contato.telefone');
    $enderecoEnc = urlencode($endereco);
    $mapaSrc = "https://maps.google.com/maps?q={$enderecoEnc}&z=13&hl=pt-BR&output=embed";
@endphp
<section id="fale-conosco" class="section-site bg-white">
    <div class="container-site">
        <div class="text-center mb-12">
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-slate-900">{{ $data['titulo'] ?? 'Onde estamos' }}</h2>
            @if(!empty($data['subtitulo']))
                <p class="mt-3 text-slate-600 max-w-2xl mx-auto">{{ $data['subtitulo'] }}</p>
            @endif
        </div>

        <div class="grid lg:grid-cols-[1fr,1.2fr] gap-8 items-stretch">
            <div class="space-y-4">
                <div class="card">
                    <div class="card-body space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center flex-shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Localização</div>
                                <div class="text-slate-900 font-medium">{{ $endereco }}</div>
                            </div>
                        </div>

                        @if($email)
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">E-mail</div>
                                    <a href="mailto:{{ $email }}" class="text-slate-900 font-medium hover:text-macaybas-primary">{{ $email }}</a>
                                </div>
                            </div>
                        @endif

                        @if($telefone)
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center flex-shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Telefone</div>
                                    <a href="tel:{{ apenasDigitos($telefone) }}" class="text-slate-900 font-medium hover:text-macaybas-primary">{{ telefoneMask($telefone) }}</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <div class="rounded-2xl overflow-hidden ring-1 ring-slate-200 shadow-sm min-h-[420px]">
                <iframe
                    src="{{ $mapaSrc }}"
                    class="w-full h-full min-h-[420px] border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                    title="Localização — {{ $endereco }}">
                </iframe>
            </div>
        </div>
    </div>
</section>
