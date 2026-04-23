@php
    // Filtra o menu pelo tenant ativo (mesma lógica do header).
    $tenantId = app()->bound('tenant_id') ? (int) app('tenant_id') : 1;
    $footerMenu = \App\Models\Cms\Menu::with('rootItems')
        ->where('tenant_id', $tenantId)
        ->where('slug', 'footer-institucional')
        ->first();
    $ano = now()->year;
    $siteNome = \App\Models\Setting::getValue('site.nome', 'Fazenda Macaybas');
    $email = \App\Models\Setting::getValue('contato.email');
    $telefone = \App\Models\Setting::getValue('contato.telefone');
    $endereco = \App\Models\Setting::getValue('contato.endereco');
    $instagram = \App\Models\Setting::getValue('social.instagram');
    $facebook = \App\Models\Setting::getValue('social.facebook');
    $youtube = \App\Models\Setting::getValue('social.youtube');
@endphp

<footer id="contato" class="bg-macaybas-primary-950 text-slate-300">
    <div class="container-site py-16">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-10 w-10 rounded-full bg-white text-macaybas-primary-900 flex items-center justify-center font-serif text-xl font-bold">M</div>
                    <div class="font-serif text-xl font-bold text-white">{{ $siteNome }}</div>
                </div>
                <p class="text-sm leading-relaxed">
                    {{ \App\Models\Setting::getValue('site.descricao', 'Tradição, produção e cuidado com a terra.') }}
                </p>
            </div>

            <div>
                <h3 class="text-white font-semibold mb-4">Contato</h3>
                <ul class="space-y-2 text-sm">
                    @if($endereco)<li class="flex gap-2"><span>📍</span><span>{{ $endereco }}</span></li>@endif
                    @if($email)<li class="flex gap-2"><span>✉️</span><a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a></li>@endif
                    @if($telefone)<li class="flex gap-2"><span>📞</span><a href="tel:{{ apenasDigitos($telefone) }}" class="hover:text-white">{{ telefoneMask($telefone) }}</a></li>@endif
                </ul>
            </div>

            <div>
                <h3 class="text-white font-semibold mb-4">Navegação</h3>
                <ul class="space-y-2 text-sm">
                    @if($footerMenu)
                        @foreach($footerMenu->rootItems as $item)
                            @if($item->is_active)
                                <li><a href="{{ $item->url }}" class="hover:text-white">{{ $item->label }}</a></li>
                            @endif
                        @endforeach
                    @endif
                </ul>
            </div>

            <div>
                <h3 class="text-white font-semibold mb-4">Redes sociais</h3>
                <div class="flex gap-3">
                    @if($instagram)
                        <a href="{{ $instagram }}" target="_blank" rel="noopener" aria-label="Instagram"
                           class="h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    @endif
                    @if($facebook)
                        <a href="{{ $facebook }}" target="_blank" rel="noopener" aria-label="Facebook"
                           class="h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                        </a>
                    @endif
                    @if($youtube)
                        <a href="{{ $youtube }}" target="_blank" rel="noopener" aria-label="YouTube"
                           class="h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-white/10 text-center text-xs text-slate-400">
            &copy; {{ $ano }} {{ $siteNome }} — Todos os direitos reservados.
            <span class="mx-2">•</span>
            <a href="{{ route('login') }}" class="hover:text-white">Área restrita</a>
        </div>
    </div>
</footer>
