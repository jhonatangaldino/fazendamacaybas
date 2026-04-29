// Fase 2: callouts em listas/hubs do manual usuário + prints faltantes do master
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
    console.log(`  → ${aplicados}/${edits.length} aplicados em ${arquivo}\n`);
}

function callout(num, pos, cor = '') {
    const c = cor ? `callout ${cor}` : 'callout';
    return `            <div class="${c}" style="${pos}">${num}</div>`;
}

// Bloco "wraps" — converte um print SIMPLES em print COM callouts.
// O `imgEantes` é o HTML antigo da imagem.
function wrap(imgSrc, alt, legenda, callouts, listaItems) {
    const calloutsDiv = callouts.map((c, i) => {
        const cor = c.cor || (i < 2 ? '' : i < 3 ? 'azul' : i < 4 ? 'laranja' : 'verde');
        return callout(i + 1, c.pos, cor);
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

console.log('=== FASE 2 · Manual usuário (callouts em listas/hubs) ===\n');

processar('manual/manual-fazenda-macaybas.html', [
    // 5. Trocar senha — callouts no avatar dropdown
    {
        label: '5. Avatar dropdown · callouts',
        antes: `    <div class="caixa-print pequena">
        <img src="screenshots/desktop/avatar-dropdown.png" alt="Dropdown do avatar">
        <div class="legenda">Clique no avatar (topo direito) → opções aparecem</div>
    </div>`,
        depois: `    ` + wrap(
            'screenshots/desktop/avatar-dropdown.png',
            'Dropdown do avatar',
            'Avatar (topo direito) · clique abre o menu',
            [
                { pos: 'top: 8%; right: 6%;' },
                { pos: 'top: 30%; right: 6%;' },
                { pos: 'top: 55%; right: 6%;', cor: 'azul' },
                { pos: 'top: 80%; right: 6%;', cor: 'laranja' },
            ],
            [
                '<strong>Avatar</strong>: clique aqui pra abrir o menu de opções da sua conta.',
                '<strong>Alterar foto</strong>: troca sua foto de perfil.',
                '<strong>Alterar senha</strong>: vai pra tela de troca de senha.',
                '<strong>Sair</strong>: fecha sua sessão (importante em PCs compartilhados).',
            ]
        )
    },
    // 9. Trocar fazenda
    {
        label: '9. Trocar fazenda · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/farm-selecionar.png" alt="Seletor de fazendas">
        <div class="legenda">Tela de seleção de fazenda · ativa fica destacada</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/farm-selecionar.png',
            'Seletor de fazendas',
            'Tela de seleção · cada fazenda em um card',
            [
                { pos: 'top: 28%; left: 50%;' },
                { pos: 'top: 50%; left: 30%;' },
                { pos: 'top: 50%; left: 60%;', cor: 'azul' },
            ],
            [
                '<strong>Lista de fazendas</strong>: todas as fazendas que sua conta tem acesso.',
                '<strong>Fazenda ativa</strong>: a que você está usando agora (destacada em verde).',
                '<strong>Outras fazendas</strong>: clique pra trocar — sistema atualiza tudo (animais, contas, etc.).',
            ]
        )
    },
    // 17. Ordenha
    {
        label: '17. Ordenha · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-controle-leiteiro.png" alt="Wizard controle leiteiro">
        <div class="legenda">Wizard de controle leiteiro · uma vaca por linha, várias ordenhas/dia</div>
    </div>`,
        depois: wrap(
            'screenshots/wizards/wizard-controle-leiteiro.png',
            'Wizard controle leiteiro',
            'Controle leiteiro · cada vaca em uma linha, ordenhas em colunas',
            [
                { pos: 'top: 22%; left: 22%;' },
                { pos: 'top: 40%; left: 30%;' },
                { pos: 'top: 60%; left: 50%;', cor: 'azul' },
                { pos: 'top: 88%; right: 12%;', cor: 'verde' },
            ],
            [
                '<strong>Stepper</strong>: passo "A data" → "As vacas" → "Conferência" → "Pronto".',
                '<strong>Data</strong>: dia das ordenhas. Padrão = hoje.',
                '<strong>Cards das vacas</strong>: cada vaca em lactação aparece com campo pra litros.',
                '<strong>Salvar</strong>: persiste todas as ordenhas de uma vez no controle do mês.',
            ]
        )
    },
    // 18. Mover lote
    {
        label: '18. Mover lote · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-mover-lote.png" alt="Wizard mover de lote">
        <div class="legenda">Wizard "Mover animal de lote" · escolha animal/lote/pasto</div>
    </div>`,
        depois: wrap(
            'screenshots/wizards/wizard-mover-lote.png',
            'Wizard mover lote',
            'Wizard "Mover animal de lote" · 4 modos de selecionar',
            [
                { pos: 'top: 22%; left: 22%;' },
                { pos: 'top: 40%; left: 22%;' },
                { pos: 'top: 40%; left: 41%;', cor: 'azul' },
                { pos: 'top: 40%; left: 60%;', cor: 'laranja' },
                { pos: 'top: 40%; left: 79%;', cor: 'verde' },
            ],
            [
                '<strong>Stepper</strong>: O animal → Pra qual lote vai → Conferência → Pronto.',
                '<strong>Um animal</strong>: move um específico (ex.: o boi mais novo da Sede).',
                '<strong>Lote inteiro</strong>: move todos os ativos de um lote pra outro.',
                '<strong>Pasto inteiro</strong>: move todos os animais de um pasto/curral.',
                '<strong>Escolher vários</strong>: seleção custom (ex.: 5 bezerros específicos).',
            ]
        )
    },
    // 19. Exame de toque
    {
        label: '19. Exame de toque · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-exame-toque.png" alt="Wizard exame de toque">
        <div class="legenda">Wizard "Exame de toque" · positivo cria automaticamente tarefa de parto</div>
    </div>`,
        depois: wrap(
            'screenshots/wizards/wizard-exame-toque.png',
            'Wizard exame de toque',
            'Exame de toque (palpação) · positivo cria tarefa de parto automática',
            [
                { pos: 'top: 22%; left: 22%;' },
                { pos: 'top: 40%; left: 30%;' },
                { pos: 'top: 60%; left: 50%;', cor: 'azul' },
                { pos: 'top: 88%; right: 12%;', cor: 'verde' },
            ],
            [
                '<strong>Stepper</strong>: A vaca → Resultado → Conferência → Pronto.',
                '<strong>Escolha a fêmea</strong>: lista mostra apenas vacas com cobertura registrada.',
                '<strong>Resultado</strong>: positivo (prenhe) ou negativo. Positivo cria tarefa "Registrar parto" auto.',
                '<strong>Salvar</strong>: registra evento e dispara tarefa programada.',
            ]
        )
    },
    // 20. Mortalidade
    {
        label: '20. Mortalidade · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-mortalidade.png" alt="Wizard mortalidade">
        <div class="legenda">Wizard "Registrar morte do animal"</div>
    </div>`,
        depois: wrap(
            'screenshots/wizards/wizard-mortalidade.png',
            'Wizard mortalidade',
            'Registrar morte · individual, lote, pasto, vários ou massa',
            [
                { pos: 'top: 22%; left: 22%;' },
                { pos: 'top: 40%; left: 18%;' },
                { pos: 'top: 40%; left: 36%;', cor: 'azul' },
                { pos: 'top: 40%; left: 56%;', cor: 'laranja' },
                { pos: 'top: 40%; left: 74%;', cor: 'verde' },
            ],
            [
                '<strong>Stepper</strong>: O animal → O que aconteceu → Conferência → Pronto.',
                '<strong>Um animal</strong>: morte de um animal específico.',
                '<strong>Lote inteiro</strong>: morte de lote inteiro (ex.: doença em galinheiro).',
                '<strong>Pasto inteiro</strong>: todos os animais de um pasto/curral.',
                '<strong>Escolher vários / Lote agregado</strong>: seleção custom OU N de M cabeças.',
            ]
        )
    },
    // 29. Marcar paga
    {
        label: '29. Marcar paga · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · ícone "Quitar" verde aparece em cada despesa pendente</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/financeiro-transacoes-lista.png',
            'Lista de transações',
            'Lista de transações · cada linha tem ações inline',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 38%; left: 30%;' },
                { pos: 'top: 60%; right: 14%;', cor: 'azul' },
                { pos: 'top: 60%; right: 6%;', cor: 'laranja' },
            ],
            [
                '<strong>KPIs</strong>: Receita do mês · Despesa do mês · Saldo · Atrasadas.',
                '<strong>Filtros</strong>: período, tipo, status, categoria.',
                '<strong>Ícone "Quitar" verde</strong>: aparece nas linhas pendentes — clique pra marcar como paga.',
                '<strong>Ícone editar / excluir</strong>: corrige ou remove a transação.',
            ]
        )
    },
    // 30. Estornar
    {
        label: '30. Estornar · reaproveita lista de transações',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · clique numa transação paga pra abrir e ver opção "Estornar"</div>
    </div>`,
        depois: `    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · clique numa paga (verde) pra abrir e ver opção "Estornar"</div>
    </div>`
    },
    // 33. Faturas
    {
        label: '33. Faturas · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/22-faturas.png" alt="Faturas">
        <div class="legenda">Suas faturas (mensalidade do sistema)</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/22-faturas.png',
            'Suas faturas',
            'Faturas · mensalidade do sistema',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 38%; left: 30%;' },
                { pos: 'top: 50%; left: 50%;', cor: 'azul' },
                { pos: 'top: 50%; right: 14%;', cor: 'laranja' },
            ],
            [
                '<strong>Cards do topo</strong>: Total a pagar / Atrasadas / Pagas.',
                '<strong>Filtro por status</strong>: aberto, atrasada, paga, em validação.',
                '<strong>Linha da fatura</strong>: clique pra ver detalhes + boleto/PIX.',
                '<strong>Status</strong>: 🟡 aberta · 🟢 paga · 🔴 atrasada · 🔵 em validação.',
            ]
        )
    },
    // 34. Agrícola Hub
    {
        label: '34. Agrícola Hub · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/30-agricola-hub.png" alt="Hub Agrícola">
        <div class="legenda">Hub Agrícola</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/30-agricola-hub.png',
            'Hub Agrícola',
            'Hub Agrícola · ações + indicadores',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 36%; left: 30%;' },
                { pos: 'top: 50%; left: 50%;', cor: 'azul' },
                { pos: 'top: 78%; left: 50%;', cor: 'laranja' },
            ],
            [
                '<strong>KPIs</strong>: Talhões · Plantios ativos · Safras em andamento.',
                '<strong>Atalhos rápidos</strong>: + Talhão, + Plantio, Aplicar produto.',
                '<strong>Plantios em andamento</strong>: cada um com status (plantado, em crescimento, colhido).',
                '<strong>Próximas aplicações</strong>: tarefas relacionadas (vencer em N dias).',
            ]
        )
    },
    // 36. Estoque Hub
    {
        label: '36. Estoque Hub · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/40-estoque-hub.png" alt="Hub Estoque">
        <div class="legenda">Hub Estoque</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/40-estoque-hub.png',
            'Hub Estoque',
            'Hub Estoque · controle de insumos (ração, sementes, vacinas)',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 36%; left: 30%;' },
                { pos: 'top: 50%; left: 50%;', cor: 'azul' },
            ],
            [
                '<strong>KPIs</strong>: Total de itens · Itens abaixo do mínimo · Movimentações do mês.',
                '<strong>Atalhos</strong>: + Item, Receber mercadoria, Saída de estoque.',
                '<strong>Itens em alerta</strong>: produtos abaixo do estoque mínimo (em amarelo).',
            ]
        )
    },
    // 37. Receber mercadoria
    {
        label: '37. Receber mercadoria · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-receber-mercadoria.png" alt="Wizard receber mercadoria">
        <div class="legenda">Wizard "Receber mercadoria" · entrada no estoque + despesa criada</div>
    </div>`,
        depois: wrap(
            'screenshots/wizards/wizard-receber-mercadoria.png',
            'Wizard receber mercadoria',
            'Wizard "Receber mercadoria" · entrada no estoque + despesa auto',
            [
                { pos: 'top: 22%; left: 22%;' },
                { pos: 'top: 40%; left: 30%;' },
                { pos: 'top: 55%; left: 50%;', cor: 'azul' },
                { pos: 'top: 70%; left: 30%;', cor: 'laranja' },
                { pos: 'top: 88%; right: 12%;', cor: 'verde' },
            ],
            [
                '<strong>Stepper</strong>: O item → Quantidade → Conferência → Pronto.',
                '<strong>Item</strong>: do catálogo (se não tem, cadastre antes).',
                '<strong>Quantidade</strong>: kg, litros ou unidades recebidas.',
                '<strong>Fornecedor + NF</strong>: opcional mas recomendado pra rastreabilidade.',
                '<strong>Continuar</strong>: avança. Final cria entrada estoque + despesa pendente.',
            ]
        )
    },
    // 39. Máquinas Hub
    {
        label: '39. Máquinas Hub · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/50-maquinas-hub.png" alt="Hub Máquinas">
        <div class="legenda">Hub Máquinas</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/50-maquinas-hub.png',
            'Hub Máquinas',
            'Hub Máquinas · veículos + manutenções',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 36%; left: 30%;' },
                { pos: 'top: 50%; left: 50%;', cor: 'azul' },
            ],
            [
                '<strong>KPIs</strong>: Total de veículos · Manutenções abertas · Custo médio.',
                '<strong>Atalhos</strong>: + Veículo, + Manutenção (OS).',
                '<strong>Próximas manutenções</strong>: preventivas vencendo nos próximos 30 dias.',
            ]
        )
    },
    // 42. Documentos
    {
        label: '42. Documentos · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/desktop/70-documentos-lista.png" alt="Documentos">
        <div class="legenda">Lista de documentos</div>
    </div>`,
        depois: wrap(
            'screenshots/desktop/70-documentos-lista.png',
            'Lista de documentos',
            'Documentos · contratos, NFs, certificados, GTAs',
            [
                { pos: 'top: 22%; left: 50%;' },
                { pos: 'top: 36%; left: 30%;' },
                { pos: 'top: 50%; left: 50%;', cor: 'azul' },
                { pos: 'top: 50%; right: 14%;', cor: 'laranja' },
            ],
            [
                '<strong>KPIs</strong>: Total · Vencendo em 30 dias · Vencidos.',
                '<strong>Filtros</strong>: categoria, vinculado a (animal/veículo/parceiro), validade.',
                '<strong>Linha do documento</strong>: clique pra ver/baixar o arquivo anexado.',
                '<strong>Status colorido</strong>: 🟢 válido · 🟡 vencendo · 🔴 vencido.',
            ]
        )
    },
    // 49. Mob fluxos
    {
        label: '49. Mobile fluxos · callouts',
        antes: `    <div class="caixa-print mobile">
        <img src="screenshots/mobile/modal-pesagem.png" alt="Wizard pesagem mobile">
        <div class="legenda">Wizard de pesagem no celular</div>
    </div>`,
        depois: `    <div class="caixa-print mobile">
        <div class="print-com-callouts">
            <img src="screenshots/mobile/modal-pesagem.png" alt="Wizard pesagem mobile">
            <div class="callout" style="top: 16%; left: 8%;">1</div>
            <div class="callout" style="top: 38%; left: 50%;">2</div>
            <div class="callout azul" style="top: 60%; left: 50%;">3</div>
            <div class="callout verde" style="top: 88%; right: 12%;">4</div>
        </div>
        <div class="legenda">Wizard de pesagem no celular · campos grandes pra dedos</div>
    </div>

    <div class="callouts-lista">
        <ol>
            <li><strong>Stepper</strong>: passo atual destacado em verde no topo.</li>
            <li><strong>Animal</strong>: lista vertical (uma por linha — fácil tocar).</li>
            <li><strong>Peso (kg)</strong>: teclado numérico abre automático.</li>
            <li><strong>Salvar</strong>: botão grande, fácil acertar com polegar.</li>
        </ol>
    </div>`
    },
]);

console.log('=== FASE 2 · Manual master (prints faltantes) ===\n');

processar('manual/manual-master.html', [
    // 3. Login
    {
        label: '3. Login master · print',
        antes: `<!-- ============================== 3. COMO ENTRAR ============================== -->
<section class="pagina" id="login">
    <h1 class="titulo-secao">3. Como entrar no sistema</h1>
    <p class="subtitulo-secao">Acesso à área Master</p>

    <div class="passo">`,
        depois: `<!-- ============================== 3. COMO ENTRAR ============================== -->
<section class="pagina" id="login">
    <h1 class="titulo-secao">3. Como entrar no sistema</h1>
    <p class="subtitulo-secao">Acesso à área Master</p>

    <div class="caixa-print pequena">
        <img src="screenshots/desktop/01-login.png" alt="Tela de login">
        <div class="legenda">Tela de login · master entra com e-mail + senha</div>
    </div>

    <div class="passo">`
    },
    // 6. Criar cliente
    {
        label: '6. Criar cliente · print',
        antes: `<!-- ============================== 6. CRIAR TENANT ============================== -->
<section class="pagina" id="criar-cliente">
    <h1 class="titulo-secao">6. Criar novo cliente</h1>
    <p class="subtitulo-secao">Cadastro inicial de um cliente novo</p>

    <div class="passo">`,
        depois: `<!-- ============================== 6. CRIAR CLIENTE ============================== -->
<section class="pagina" id="criar-cliente">
    <h1 class="titulo-secao">6. Criar novo cliente</h1>
    <p class="subtitulo-secao">Cadastro inicial de um cliente novo</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-criar-cliente.png" alt="Form criar cliente">
        <div class="legenda">Formulário de novo cliente</div>
    </div>

    <div class="passo">`
    },
    // 7. Editar/suspender
    {
        label: '7. Editar/suspender · print',
        antes: `<!-- ============================== 7. EDITAR / SUSPENDER ============================== -->
<section class="pagina" id="editar-cliente">
    <h1 class="titulo-secao">7. Editar / suspender cliente</h1>
    <p class="subtitulo-secao">Manutenção de clientes existentes</p>

    <h2>Editar dados</h2>`,
        depois: `<!-- ============================== 7. EDITAR / SUSPENDER ============================== -->
<section class="pagina" id="editar-cliente">
    <h1 class="titulo-secao">7. Editar / suspender cliente</h1>
    <p class="subtitulo-secao">Manutenção de clientes existentes</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-tenant-editar.png" alt="Editar cliente">
        <div class="legenda">Tela de edição do cliente</div>
    </div>

    <h2>Editar dados</h2>`
    },
    // 9. Reset senha
    {
        label: '9. Reset senha · print usuários do cliente',
        antes: `<!-- ============================== 9. RESET SENHA ============================== -->
<section class="pagina" id="usuarios-cliente">
    <h1 class="titulo-secao">9. Reset de senha de funcionários</h1>
    <p class="subtitulo-secao">Cliente liga reclamando que perdeu acesso</p>

    <p>Caso de uso comum`,
        depois: `<!-- ============================== 9. RESET SENHA ============================== -->
<section class="pagina" id="usuarios-cliente">
    <h1 class="titulo-secao">9. Reset de senha de funcionários</h1>
    <p class="subtitulo-secao">Cliente liga reclamando que perdeu acesso</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-tenant-usuarios.png" alt="Usuários do cliente">
        <div class="legenda">Tela "Usuários" do cliente · cada linha tem ações</div>
    </div>

    <p>Caso de uso comum`
    },
    // 11. Assinaturas
    {
        label: '11. Assinaturas · print',
        antes: `<!-- ============================== 11. ASSINATURAS ============================== -->
<section class="pagina" id="assinaturas">
    <h1 class="titulo-secao">11. Assinaturas dos clientes</h1>
    <p class="subtitulo-secao">Plano ativo de cada cliente</p>

    <p>Cada cliente tem 1 assinatura`,
        depois: `<!-- ============================== 11. ASSINATURAS ============================== -->
<section class="pagina" id="assinaturas">
    <h1 class="titulo-secao">11. Assinaturas dos clientes</h1>
    <p class="subtitulo-secao">Plano ativo de cada cliente</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-tenant-assinatura.png" alt="Assinatura do cliente">
        <div class="legenda">Tela de assinatura · plano + status + valor</div>
    </div>

    <p>Cada cliente tem 1 assinatura`
    },
    // 13. Wizard faturas
    {
        label: '13. Wizard gerar faturas · print',
        antes: `<!-- ============================== 13. WIZARD FATURAS ============================== -->
<section class="pagina" id="wizard-faturas">
    <h1 class="titulo-secao">13. Gerar faturas em lote</h1>
    <p class="subtitulo-secao">Cobrança mensal automatizada</p>

    <p>Mensalmente`,
        depois: `<!-- ============================== 13. WIZARD FATURAS ============================== -->
<section class="pagina" id="wizard-faturas">
    <h1 class="titulo-secao">13. Gerar faturas em lote</h1>
    <p class="subtitulo-secao">Cobrança mensal automatizada</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-gerar-faturas.png" alt="Wizard gerar faturas">
        <div class="legenda">Wizard "Gerar faturas" · mensal ou única</div>
    </div>

    <p>Mensalmente`
    },
    // 19. Enviar manual
    {
        label: '19. Enviar manual · print',
        antes: `<!-- ============================== 19. ENVIAR MANUAL ============================== -->
<section class="pagina" id="enviar-manual">
    <h1 class="titulo-secao">19. Enviar manual por e-mail</h1>
    <p class="subtitulo-secao">Fluxo automatizado · cliente → dono ativo</p>

    <div class="passo">`,
        depois: `<!-- ============================== 19. ENVIAR MANUAL ============================== -->
<section class="pagina" id="enviar-manual">
    <h1 class="titulo-secao">19. Enviar manual por e-mail</h1>
    <p class="subtitulo-secao">Fluxo automatizado · cliente → dono ativo</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-manuais.png" alt="Master · Manuais">
        <div class="legenda">Tela de manuais · cards "Baixar" + "Enviar manual"</div>
    </div>

    <div class="passo">`
    },
    // 21. CMS Seções
    {
        label: '21. CMS seções · print',
        antes: `<section class="pagina" id="cms-secoes">
    <h1 class="titulo-secao">21. Editar seções, banners, galeria</h1>
    <p class="subtitulo-secao">Como mudar conteúdo da landing</p>

    <div class="passo">`,
        depois: `<section class="pagina" id="cms-secoes">
    <h1 class="titulo-secao">21. Editar seções, banners, galeria</h1>
    <p class="subtitulo-secao">Como mudar conteúdo da landing</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cliente-cms.png" alt="CMS por cliente">
        <div class="legenda">CMS do cliente · editar seções da landing pública</div>
    </div>

    <div class="passo">`
    },
    // 10. Planos · callouts
    {
        label: '10. Planos · callouts',
        antes: `    <div class="caixa-print">
        <img src="screenshots/master/master-planos.png" alt="Master · Planos">
        <div class="legenda">Catálogo de planos</div>
    </div>`,
        depois: `<div class="caixa-print">
        <div class="print-com-callouts">
            <img src="screenshots/master/master-planos.png" alt="Master · Planos">
            <div class="callout" style="top: 22%; left: 50%;">1</div>
            <div class="callout" style="top: 36%; left: 30%;">2</div>
            <div class="callout azul" style="top: 50%; left: 50%;">3</div>
            <div class="callout laranja" style="top: 50%; right: 14%;">4</div>
        </div>
        <div class="legenda">Catálogo de planos · cada plano em um card</div>
    </div>

    <div class="callouts-lista">
        <ol>
            <li><strong>KPIs</strong>: total de planos ativos · clientes por plano.</li>
            <li><strong>+ Novo plano</strong>: cria um plano novo no catálogo.</li>
            <li><strong>Card do plano</strong>: nome, preço, limites (fazendas/usuários).</li>
            <li><strong>Ações</strong>: editar · ativar/desativar plano.</li>
        </ol>
    </div>`
    },
]);

console.log('\n✅ Fase 2 concluída.');
