@extends('site.layouts.public')

@section('title', 'Política de Privacidade · Fazenda Macaybas')
@section('meta_description', 'Política de privacidade e tratamento de dados pessoais da Fazenda Macaybas, em conformidade com a LGPD (Lei 13.709/2018).')

@section('content')
<section class="py-16 lg:py-24 bg-white">
    <div class="container mx-auto px-4 max-w-3xl prose prose-slate">
        <h1 class="text-3xl md:text-4xl font-serif font-bold text-slate-900 mb-2">Política de Privacidade</h1>
        <p class="text-sm text-slate-500 mb-10">Última atualização: 29 de abril de 2026</p>

        <h2 class="text-xl font-semibold mt-8 mb-3">1. Quem somos</h2>
        <p>A <strong>Fazenda Macaybas</strong> ("nós") é responsável pelo tratamento dos dados pessoais coletados através deste site e dos serviços relacionados, em conformidade com a Lei Geral de Proteção de Dados (Lei 13.709/2018 — LGPD).</p>

        <h2 class="text-xl font-semibold mt-8 mb-3">2. Dados que coletamos</h2>
        <ul class="list-disc pl-6 space-y-1">
            <li><strong>Navegação:</strong> dados técnicos do navegador (User-Agent, IP, páginas visitadas) através de logs de servidor padrão.</li>
            <li><strong>Contato:</strong> nome, e-mail e telefone, quando você nos envia uma mensagem.</li>
            <li><strong>Operação (clientes do sistema):</strong> dados cadastrais (CPF/CNPJ, endereço), informações financeiras (sem dados bancários sensíveis), e dados operacionais da fazenda (animais, plantios, transações).</li>
            <li><strong>Cookies de terceiros:</strong> Google Maps embedado pode coletar dados de geolocalização do seu navegador.</li>
        </ul>

        <h2 class="text-xl font-semibold mt-8 mb-3">3. Como usamos seus dados</h2>
        <ul class="list-disc pl-6 space-y-1">
            <li>Operacionalizar o sistema contratado pelo cliente (multi-tenant ERP).</li>
            <li>Responder ao seu contato.</li>
            <li>Cumprir obrigações legais (fiscais, contábeis, regulatórias).</li>
            <li>Auditoria interna e detecção de fraude (defesa do controlador).</li>
        </ul>

        <h2 class="text-xl font-semibold mt-8 mb-3">4. Com quem compartilhamos</h2>
        <ul class="list-disc pl-6 space-y-1">
            <li><strong>Hostinger</strong> — provedor de hospedagem (Brasil/EUA). <a href="https://www.hostinger.com.br/legal/privacy-policy" target="_blank" rel="noopener" class="text-emerald-700 underline">Política Hostinger</a>.</li>
            <li><strong>Google</strong> — Maps embedado. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="text-emerald-700 underline">Política Google</a>.</li>
            <li><strong>Bunny CDN (fonts)</strong> — entrega de fontes web. <a href="https://bunny.net/privacy" target="_blank" rel="noopener" class="text-emerald-700 underline">Política Bunny</a>.</li>
        </ul>
        <p>Não vendemos, alugamos ou cedemos seus dados a terceiros para fins comerciais.</p>

        <h2 class="text-xl font-semibold mt-8 mb-3">5. Tempo de retenção</h2>
        <ul class="list-disc pl-6 space-y-1">
            <li>Logs de servidor: 90 dias.</li>
            <li>Mensagens de contato: até 2 anos.</li>
            <li>Dados operacionais de clientes: enquanto o contrato estiver ativo, mais o prazo legal mínimo (5 anos para fiscais).</li>
        </ul>

        <h2 class="text-xl font-semibold mt-8 mb-3">6. Seus direitos (LGPD Art. 18)</h2>
        <p>Você pode, a qualquer momento:</p>
        <ul class="list-disc pl-6 space-y-1">
            <li><strong>Confirmar</strong> a existência de tratamento dos seus dados.</li>
            <li><strong>Acessar</strong> seus dados.</li>
            <li><strong>Corrigir</strong> dados incompletos ou desatualizados.</li>
            <li><strong>Anonimizar</strong>, bloquear ou eliminar dados desnecessários.</li>
            <li><strong>Portabilidade</strong> a outro fornecedor.</li>
            <li><strong>Eliminar</strong> dados tratados com base em consentimento.</li>
            <li><strong>Revogar</strong> o consentimento.</li>
        </ul>
        <p>Para exercer qualquer direito, envie e-mail para <a href="mailto:contato@fazendamacaybas.com.br" class="text-emerald-700 underline">contato@fazendamacaybas.com.br</a>. Responderemos em até 15 dias úteis.</p>

        <h2 class="text-xl font-semibold mt-8 mb-3">7. Segurança</h2>
        <p>Utilizamos HTTPS (TLS), senhas hasheadas (bcrypt), isolamento multi-tenant a nível de banco, controle de acesso baseado em papéis (RBAC) e auditoria de ações administrativas.</p>

        <h2 class="text-xl font-semibold mt-8 mb-3">8. Encarregado de Dados (DPO)</h2>
        <p>Jhonatan Galdino — <a href="mailto:contato@fazendamacaybas.com.br" class="text-emerald-700 underline">contato@fazendamacaybas.com.br</a></p>

        <h2 class="text-xl font-semibold mt-8 mb-3">9. Alterações</h2>
        <p>Esta política pode ser atualizada. Mudanças relevantes serão comunicadas pelo site com 30 dias de antecedência.</p>

        <p class="mt-12"><a href="/" class="text-emerald-700 hover:underline">← Voltar para a home</a></p>
    </div>
</section>
@endsection
