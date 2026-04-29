// Fase 5 — fechamento 100%
import fs from 'node:fs';

function processar(arquivo, edits) {
    let html = fs.readFileSync(arquivo, 'utf8');
    let aplicados = 0;
    for (const e of edits) {
        if (! html.includes(e.antes)) {
            console.log(`  ⚠️ NÃO ACHOU: ${e.label}`);
            continue;
        }
        html = html.replace(e.antes, e.depois);
        console.log(`  ✅ ${e.label}`);
        aplicados++;
    }
    fs.writeFileSync(arquivo, html);
    console.log(`  → ${aplicados}/${edits.length} aplicados\n`);
}

function wrap(imgSrc, alt, legenda, callouts, lista) {
    const cd = callouts.map((c, i) => {
        const cor = c.cor || (i < 2 ? '' : i < 3 ? 'azul' : i < 4 ? 'laranja' : 'verde');
        return `            <div class="callout ${cor}" style="${c.pos}">${i + 1}</div>`;
    }).join('\n');
    const items = lista.map(x => `            <li>${x}</li>`).join('\n');
    return `<div class="caixa-print">
        <div class="print-com-callouts">
            <img src="${imgSrc}" alt="${alt}">
${cd}
        </div>
        <div class="legenda">${legenda}</div>
    </div>

    <div class="callouts-lista">
        <ol>
${items}
        </ol>
    </div>`;
}

console.log('=== FASE 5 final — 100% ===\n');

processar('manual/manual-fazenda-macaybas.html', [
    // Como usar manual — print de Tela de Início com callouts marcando os blocos
    {
        label: 'Como usar manual · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/02-inicio-hub.png" alt="Tela de Início (exemplo)">
        <div class="legenda">A tela de Início — sua porta de entrada todos os dias</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/02-inicio-hub.png',
            'Tela de Início (exemplo)',
            'A tela de Início — sua porta de entrada · use os atalhos pra encontrar tudo',
            [
                { pos: 'top: 22%; left: 1%;' },
                { pos: 'top: 18%; left: 30%;' },
                { pos: 'top: 50%; left: 30%;', cor: 'azul' },
                { pos: 'top: 4%; right: 12%;', cor: 'laranja' },
            ],
            [
                '<strong>Menu lateral</strong>: todos os módulos do sistema (Rebanho, Financeiro, Agrícola, etc.).',
                '<strong>Saudação personalizada</strong>: "Bom dia/Boa tarde" + seu nome.',
                '<strong>Cards de ações rápidas</strong>: as ações mais usadas — clique pra começar.',
                '<strong>Topbar</strong>: trocar fazenda + dropdown do seu usuário.',
            ]
        )
    },
    // Primeiros passos — print com callouts marcando o caminho do roteiro
    {
        label: 'Primeiros passos · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/95-usuarios.png" alt="Tela de Usuários">
        <div class="legenda">Tela de Usuários · onde você cadastra a equipe (Passo 3 abaixo)</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/95-usuarios.png',
            'Tela de Usuários',
            'Tela de Usuários · onde você cadastra a equipe (Passo 3 do roteiro)',
            [
                { pos: 'top: 22%; left: 1%;' },
                { pos: 'top: 18%; right: 12%;' },
                { pos: 'top: 38%; left: 30%;', cor: 'azul' },
                { pos: 'top: 70%; right: 8%;', cor: 'laranja' },
            ],
            [
                '<strong>Sidebar → Usuários</strong>: aqui você gerencia quem tem acesso ao sistema.',
                '<strong>+ Novo usuário</strong>: cadastra cada funcionário (envia senha temp por e-mail).',
                '<strong>Filtros e busca</strong>: encontra um funcionário rápido (em fazendas grandes).',
                '<strong>Ações inline</strong>: editar perfil · resetar senha · ativar/inativar.',
            ]
        )
    },
    // Estornar — callouts apontando especificamente "transação paga" (verde) + ícone editar
    {
        label: 'Estornar · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · clique numa paga (verde) pra abrir e ver opção "Estornar"</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/financeiro-transacoes-lista.png',
            'Lista de transações',
            'Lista de transações · estornar é diferente de excluir',
            [
                { pos: 'top: 38%; left: 30%;' },
                { pos: 'top: 38%; right: 22%;' },
                { pos: 'top: 60%; right: 14%;', cor: 'azul' },
                { pos: 'top: 60%; right: 6%;', cor: 'vermelho' },
            ],
            [
                '<strong>Transação paga</strong>: linha verde indica que já foi quitada.',
                '<strong>Status "Pago"</strong>: badge verde na coluna de status.',
                '<strong>Ícone Editar (lápis)</strong>: clique pra abrir os detalhes — botão Estornar aparece nessa tela.',
                '<strong>Ícone Excluir (lixeira)</strong>: ❌ NÃO use em transações pagas — sistema sugere Estornar automaticamente.',
            ]
        )
    },
]);

processar('manual/manual-master.html', [
    // Auto-aprovação — adicionar print + callouts
    {
        label: 'Auto-aprovação · print + callouts',
        antes: `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Cobranças">
        <div class="legenda">Acesse "Configurações" no topo da tela de Cobranças pra ligar a auto-aprovação</div>
    </div>

    <h2>Como ligar</h2>
    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo"><em>Cobranças → Configurações → Auto-aprovação</em>.</div>
    </div>
    <div class="passo">
        <div class="num">2</div>
        <div class="conteudo">Toggle <strong>ON</strong>. Sistema começa a processar comprovantes em background.</div>
    </div>
    <div class="passo">
        <div class="num">3</div>
        <div class="conteudo">Quando a <em>maturidade</em> (% de acertos) chegar a 70%+, sistema aprova automaticamente os de alta confiança.</div>
    </div>

    <h2>Como ligar (alternativa)</h2>`,
        depois: `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>

    ` + wrap(
        'screenshots/master/master-cobrancas.png',
        'Cobranças com config auto-aprovação',
        'Cobranças → "Configurações" no topo · ligar auto-aprovação',
        [
            { pos: 'top: 18%; right: 22%;' },
            { pos: 'top: 18%; right: 8%;' },
            { pos: 'top: 38%; left: 30%;', cor: 'azul' },
            { pos: 'top: 60%; left: 50%;', cor: 'laranja' },
        ],
        [
            '<strong>+ Gerar faturas</strong>: ao lado, wizard pra criar mensalidades em lote (seção 13).',
            '<strong>Configurações</strong>: link no topo abre as opções de cobrança incluindo auto-aprovação.',
            '<strong>Filtro "Em validação"</strong>: depois de ligar auto-aprovação, comprovantes que falharam ficam aqui pra revisão manual.',
            '<strong>Linha da fatura</strong>: badge "Aprovação automática" aparece nas que o sistema aprovou sozinho.',
        ]
    ) + `

    <h2>Como ligar</h2>
    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo"><em>Cobranças → Configurações → Auto-aprovação</em>.</div>
    </div>
    <div class="passo">
        <div class="num">2</div>
        <div class="conteudo">Toggle <strong>ON</strong>. Sistema começa a processar comprovantes em background.</div>
    </div>
    <div class="passo">
        <div class="num">3</div>
        <div class="conteudo">Quando a <em>maturidade</em> (% de acertos) chegar a 70%+, sistema aprova automaticamente os de alta confiança.</div>
    </div>

    <h2>Como ligar (alternativa)</h2>`
    },
]);

console.log('\n✅ Fase 5 (final) salva.');
