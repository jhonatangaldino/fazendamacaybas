// Fase 3: últimas pendências
import fs from 'node:fs';

function processar(arquivo, edits) {
    let html = fs.readFileSync(arquivo, 'utf8');
    let aplicados = 0;
    for (const e of edits) {
        if (! html.includes(e.antes)) {
            console.log(`  ⚠️ ${arquivo} · NÃO ACHOU: ${e.label}`);
            continue;
        }
        html = html.replace(e.antes, e.depois);
        console.log(`  ✅ ${arquivo} · ${e.label}`);
        aplicados++;
    }
    fs.writeFileSync(arquivo, html);
    console.log(`  → ${aplicados}/${edits.length} aplicados\n`);
}

console.log('=== FASE 3 · Manual usuário ===\n');

processar('manual/manual-fazenda-macaybas.html', [
    // Como usar manual — adicionar print da capa do manual
    {
        label: 'Como usar manual · print sumário',
        antes: `    <h2>3 jeitos de usar</h2>`,
        depois: `    <div class="caixa-print">
        <img src="screenshots/desktop/02-inicio-hub.png" alt="Tela de Início (exemplo)">
        <div class="legenda">A tela de Início — sua porta de entrada todos os dias</div>
    </div>

    <h2>3 jeitos de usar</h2>`
    },
    // Primeiros passos — adicionar print da tela de início
    {
        label: 'Primeiros passos · print',
        antes: `    <p>O sistema te dá liberdade para cadastrar tudo na ordem que você quiser, mas <strong>existe uma ordem mais inteligente</strong> que evita retrabalho. Siga este roteiro no Dia 1:</p>`,
        depois: `    <p>O sistema te dá liberdade para cadastrar tudo na ordem que você quiser, mas <strong>existe uma ordem mais inteligente</strong> que evita retrabalho. Siga este roteiro no Dia 1:</p>

    <div class="caixa-print">
        <img src="screenshots/desktop/95-usuarios.png" alt="Tela de Usuários">
        <div class="legenda">Tela de Usuários · onde você cadastra a equipe (Passo 3 abaixo)</div>
    </div>`
    },
]);

console.log('=== FASE 3 · Manual master · callouts em massa ===\n');

function wrapMaster(imgSrc, alt, legenda, callouts, listaItems) {
    const calloutsDiv = callouts.map((c, i) => {
        const cor = c.cor || (i < 2 ? '' : i < 3 ? 'azul' : i < 4 ? 'laranja' : 'verde');
        return `            <div class="callout ${cor}" style="${c.pos}">${i + 1}</div>`;
    }).join('\n');
    const lista = listaItems.map(item => `            <li>${item}</li>`).join('\n');
    return `<div class="caixa-print">
        <div class="print-com-callouts">
            <img src="${imgSrc}" alt="${alt}">
${calloutsDiv}
        </div>
        <div class="legenda">${legenda}</div>
    </div>

    <div class="callouts-lista">
        <ol>
${lista}
        </ol>
    </div>`;
}

processar('manual/manual-master.html', [
    // 3. Login master — callouts
    {
        label: '3. Login master · callouts',
        antes: `    <div class="caixa-print pequena">
        <img src="screenshots/desktop/01-login.png" alt="Tela de login">
        <div class="legenda">Tela de login · master entra com e-mail + senha</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/desktop/01-login.png',
            'Tela de login',
            'Tela de login · master usa o mesmo formulário do cliente',
            [
                { pos: 'top: 38%; left: 67%;' },
                { pos: 'top: 50%; left: 67%;' },
                { pos: 'top: 65%; left: 67%;', cor: 'azul' },
                { pos: 'top: 58%; left: 86%;', cor: 'laranja' },
            ],
            [
                '<strong>E-mail</strong>: seu e-mail de master cadastrado.',
                '<strong>Senha</strong>: senha pessoal (definida no primeiro login).',
                '<strong>Entrar</strong>: sistema reconhece como master e leva pro Painel Master.',
                '<strong>Esqueci minha senha</strong>: link pra reset por e-mail.',
            ]
        )
    },
    // 6. Criar cliente — callouts
    {
        label: '6. Criar cliente · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-criar-cliente.png" alt="Form criar cliente">
        <div class="legenda">Formulário de novo cliente</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-criar-cliente.png',
            'Form criar cliente',
            'Formulário de novo cliente · dados básicos + plano',
            [
                { pos: 'top: 28%; left: 30%;' },
                { pos: 'top: 28%; left: 60%;' },
                { pos: 'top: 50%; left: 30%;', cor: 'azul' },
                { pos: 'top: 70%; left: 50%;', cor: 'laranja' },
            ],
            [
                '<strong>Nome da fazenda</strong> *: nome do cliente como aparece no sistema.',
                '<strong>Endereço curto</strong> *: usado em URLs (ex.: "fazenda-do-zé").',
                '<strong>Documento</strong>: CNPJ ou CPF (com validação automática).',
                '<strong>Plano</strong>: escolha do catálogo. Define preço, módulos e limites.',
            ]
        )
    },
    // 9. Reset senha — callouts
    {
        label: '9. Reset senha · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-tenant-usuarios.png" alt="Usuários do cliente">
        <div class="legenda">Tela "Usuários" do cliente · cada linha tem ações</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-tenant-usuarios.png',
            'Usuários do cliente',
            'Tela "Usuários" · cada usuário do cliente em uma linha',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 38%; right: 6%;' },
                { pos: 'top: 55%; right: 14%;', cor: 'azul' },
                { pos: 'top: 55%; right: 6%;', cor: 'laranja' },
            ],
            [
                '<strong>+ Novo usuário</strong>: cadastra funcionário novo no cliente (você master, não o dono).',
                '<strong>Resetar senha</strong>: gera senha temporária e envia por e-mail. Útil quando cliente liga reclamando de acesso.',
                '<strong>Ativar / Inativar</strong>: bloqueia login sem deletar histórico.',
                '<strong>Editar</strong>: muda perfil, nome, e-mail.',
            ]
        )
    },
    // 11. Assinaturas — callouts
    {
        label: '11. Assinaturas · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-tenant-assinatura.png" alt="Assinatura do cliente">
        <div class="legenda">Tela de assinatura · plano + status + valor</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-tenant-assinatura.png',
            'Assinatura do cliente',
            'Tela de assinatura · plano contratado + status',
            [
                { pos: 'top: 25%; left: 30%;' },
                { pos: 'top: 25%; left: 60%;' },
                { pos: 'top: 50%; left: 30%;', cor: 'azul' },
                { pos: 'top: 75%; left: 30%;', cor: 'laranja' },
            ],
            [
                '<strong>Plano atual</strong>: escolha pra mudar — efeito imediato ou no próximo ciclo.',
                '<strong>Status</strong>: ativo, suspenso, trial, cancelado.',
                '<strong>Valor mensal</strong>: pode customizar (descontos comerciais).',
                '<strong>Cancelar assinatura</strong>: cliente continua até fim do período pago.',
            ]
        )
    },
    // 13. Gerar faturas — callouts
    {
        label: '13. Gerar faturas · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-gerar-faturas.png" alt="Wizard gerar faturas">
        <div class="legenda">Wizard "Gerar faturas" · mensal ou única</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-gerar-faturas.png',
            'Wizard gerar faturas',
            'Wizard "Gerar faturas" · mensal ou única',
            [
                { pos: 'top: 25%; left: 30%;' },
                { pos: 'top: 38%; left: 30%;' },
                { pos: 'top: 55%; left: 50%;', cor: 'azul' },
                { pos: 'top: 88%; right: 12%;', cor: 'laranja' },
            ],
            [
                '<strong>Tipo</strong>: Mensal (todos clientes ativos) ou Única (1 cliente específico).',
                '<strong>Período de competência</strong>: mês de referência (ex.: "Maio/2026").',
                '<strong>Data de vencimento</strong>: quando a fatura vence (ex.: "10/05/2026").',
                '<strong>Preview / Gerar</strong>: Preview mostra tabela; Gerar cria as faturas + notifica.',
            ]
        )
    },
    // 19. Enviar manual — callouts
    {
        label: '19. Enviar manual · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-manuais.png" alt="Master · Manuais">
        <div class="legenda">Tela de manuais · cards "Baixar" + "Enviar manual"</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-manuais.png',
            'Master · Manuais',
            'Tela de Manuais · 2 ações por manual + histórico abaixo',
            [
                { pos: 'top: 25%; left: 50%;' },
                { pos: 'top: 50%; left: 30%;' },
                { pos: 'top: 50%; left: 60%;', cor: 'azul' },
                { pos: 'top: 75%; left: 50%;', cor: 'laranja' },
            ],
            [
                '<strong>Card do manual</strong>: cada manual disponível (Usuário enviável; Master interno).',
                '<strong>Baixar</strong>: download direto pro seu computador.',
                '<strong>Enviar manual</strong>: modal pra escolher cliente + dono ativo + mensagem.',
                '<strong>Histórico</strong>: tabela mostra envios anteriores e status (aberto/aguardando).',
            ]
        )
    },
    // 21. CMS Seções — callouts
    {
        label: '21. CMS seções · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-cliente-cms.png" alt="CMS por cliente">
        <div class="legenda">CMS do cliente · editar seções da landing pública</div>
    </div>`,
        depois: wrapMaster(
            'screenshots/master/master-cliente-cms.png',
            'CMS por cliente',
            'CMS · editor da landing pública do cliente',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 40%; left: 30%;' },
                { pos: 'top: 60%; left: 50%;', cor: 'azul' },
                { pos: 'top: 88%; right: 12%;', cor: 'laranja' },
            ],
            [
                '<strong>Páginas</strong>: Home, Sobre, Contato (criadas no scaffolding inicial).',
                '<strong>Seções</strong>: cada bloco editável da página (Hero, Galeria, Depoimentos…).',
                '<strong>Editar</strong>: clica numa seção pra mudar texto/imagens/CTAs.',
                '<strong>Publicar</strong>: rascunho → publicado (vai pro público).',
            ]
        )
    },
    // 8. Impersonar — adicionar print de tenants list (com botão impersonar)
    {
        label: '8. Impersonar · print',
        antes: `    <p>Quando um cliente reporta um bug ou tem dúvida operacional, em vez de pedir vídeo/print, você <strong>entra como o cliente</strong> — vira o usuário dele temporariamente e vê exatamente o que ele vê.</p>`,
        depois: `    <p>Quando um cliente reporta um bug ou tem dúvida operacional, em vez de pedir vídeo/print, você <strong>entra como o cliente</strong> — vira o usuário dele temporariamente e vê exatamente o que ele vê.</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-tenants.png" alt="Lista de clientes com ação Impersonar">
        <div class="legenda">Lista de clientes · cada linha tem ícone "🎭 Impersonar"</div>
    </div>`
    },
    // 14. Validar comprovantes — adicionar print da lista de cobranças
    {
        label: '14. Validar comprovantes · print',
        antes: `    <p>Quando o cliente paga uma fatura e clica em "Enviar comprovante" no portal dele, a fatura entra em status <em>Em validação</em>. Você precisa conferir e aprovar.</p>`,
        depois: `    <p>Quando o cliente paga uma fatura e clica em "Enviar comprovante" no portal dele, a fatura entra em status <em>Em validação</em>. Você precisa conferir e aprovar.</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Lista de cobranças">
        <div class="legenda">Lista de cobranças · filtre por "Em validação" pra ver os pendentes</div>
    </div>`
    },
    // 15. Auto-aprovação — adicionar print da config
    {
        label: '15. Auto-aprovação · print',
        antes: `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>`,
        depois: `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Tela de cobranças com config auto-aprovação">
        <div class="legenda">Configurações de cobrança · toggle de auto-aprovação fica em "Configurações"</div>
    </div>`
    },
    // 23. Boas práticas — adicionar print do dashboard
    {
        label: '23. Boas práticas · print',
        antes: `    <h2>Diário</h2>
    <ul style="padding-left: 25px; margin: 8px 0;">
        <li>Abra o painel master de manhã.`,
        depois: `    <div class="caixa-print">
        <img src="screenshots/master/master-dashboard.png" alt="Painel Master">
        <div class="legenda">Painel Master · sua referência diária pra rotina operacional</div>
    </div>

    <h2>Diário</h2>
    <ul style="padding-left: 25px; margin: 8px 0;">
        <li>Abra o painel master de manhã.`
    },
]);

console.log('\n✅ Fase 3 concluída.');
