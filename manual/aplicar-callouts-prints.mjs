// Aplica em massa: prints faltantes + callouts numerados
// Estratégia: posicionar callouts em coordenadas % padronizadas que casam
// com a estrutura típica de cada tipo de tela (wizard, form, lista).
//
// Padrão de wizard (header + stepper + form + botão):
//   1 → top-left do wizard (stepper passo 1)
//   2 → meio (campo principal / cards de escolha)
//   3 → meio-baixo (campo secundário ou lista)
//   4 → bottom-right (botão Continuar/Salvar)
//
// Padrão de form simples:
//   1 → topo do form (primeiro campo)
//   2-N → campos seguintes
//   N+1 → botão Salvar
//
// Padrão de lista (Hub/Listagem):
//   1 → KPI no topo
//   2 → filtros
//   3 → linha da lista
//   4 → botão "+ Novo X"

import fs from 'node:fs';

const FILE = 'manual/manual-fazenda-macaybas.html';
let html = fs.readFileSync(FILE, 'utf8');

// Helper: substitui um padrão garantindo só 1 match
function substituir(antes, depois, label) {
    if (! html.includes(antes)) {
        console.log(`  ⚠️ NÃO ACHOU: ${label}`);
        return;
    }
    html = html.replace(antes, depois);
    console.log(`  ✅ ${label}`);
}

// ─── Helper: gera bloco de print com callouts ───
function printComCallouts(imgSrc, alt, legenda, callouts, listaItems) {
    const calloutsDiv = callouts.map((c, i) => {
        const cor = c.cor || (i < 2 ? '' : i < 3 ? 'azul' : i < 4 ? 'laranja' : 'verde');
        const classe = cor ? `callout ${cor}` : 'callout';
        return `            <div class="${classe}" style="${c.pos}">${i + 1}</div>`;
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

console.log('=== Aplicando callouts/prints em massa ===\n');

// ─── 15. PESAR ANIMAL ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/modais/modal-pesagem.png" alt="Wizard de pesagem">
        <div class="legenda">Wizard de pesagem · escolha o animal, informe o peso, salve</div>
    </div>`,
    printComCallouts(
        'screenshots/modais/modal-pesagem.png',
        'Wizard de pesagem',
        'Wizard de pesagem · cada bolinha aponta um campo',
        [
            { pos: 'top: 22%; left: 16%;' },
            { pos: 'top: 39%; left: 22%;' },
            { pos: 'top: 39%; left: 41%;', cor: 'azul' },
            { pos: 'top: 70%; left: 30%;', cor: 'laranja' },
            { pos: 'top: 88%; right: 8%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: você está no passo "O que pesar" (mostra os 4 passos do wizard).',
            '<strong>Animal individual</strong>: pesa um boi por vez (caso mais comum).',
            '<strong>Pesagem amostral / Biomassa</strong>: pra Ave/Peixe — pesa amostra e calcula peso médio.',
            '<strong>Lista de animais</strong>: toque no animal pra escolher. Mostra brinco, mãe e último peso.',
            '<strong>Continuar</strong>: avança pro próximo passo (informar o peso).',
        ]
    ),
    '15. Pesar — callouts'
);

// ─── 16. VACINAR ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/modais/modal-vacinacao.png" alt="Wizard de vacinação">
        <div class="legenda">Wizard de vacinação · individual ou lote inteiro</div>
    </div>`,
    printComCallouts(
        'screenshots/modais/modal-vacinacao.png',
        'Wizard de vacinação',
        'Wizard de vacinação · 5 modos de aplicar',
        [
            { pos: 'top: 22%; left: 16%;' },
            { pos: 'top: 41%; left: 18%;' },
            { pos: 'top: 41%; left: 38%;', cor: 'azul' },
            { pos: 'top: 41%; left: 56%;', cor: 'laranja' },
            { pos: 'top: 41%; left: 76%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: passo "O animal" (escolher quem vacina).',
            '<strong>Um animal</strong>: vacina apenas um animal específico.',
            '<strong>Lote inteiro</strong>: vacina todos os animais de um lote (ex.: lote galinhas).',
            '<strong>Pasto inteiro</strong>: vacina todos que estão num pasto/curral.',
            '<strong>Escolher vários / Lote agregado</strong>: vacina seleção custom OU N de M cabeças.',
        ]
    ),
    '16. Vacinar — callouts'
);

// ─── 25. VENDER ANIMAL ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-venda-animal.png" alt="Wizard de venda">
        <div class="legenda">Wizard de venda de animal</div>
    </div>`,
    printComCallouts(
        'screenshots/wizards/wizard-venda-animal.png',
        'Wizard de venda',
        'Wizard de venda · 5 modos · cada bolinha aponta um modo',
        [
            { pos: 'top: 22%; left: 16%;' },
            { pos: 'top: 38%; left: 26%;' },
            { pos: 'top: 38%; left: 64%;', cor: 'azul' },
            { pos: 'top: 56%; left: 26%;', cor: 'laranja' },
            { pos: 'top: 56%; left: 64%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: 6 passos guiados — O que vender, Seleção, Comprador, Preço, Conferir, Pronto.',
            '<strong>Um animal</strong>: vende um animal específico (ex.: o cavalo, a matriz).',
            '<strong>Vários animais</strong>: lista pra escolher (ex.: 5 bovinos pro frigorífico).',
            '<strong>Lote / tanque inteiro</strong>: vende todos os ativos de um lote ou tanque.',
            '<strong>Quantidade do lote / Por peso</strong>: massa sem identificar individual (300 frangos / 200 kg de tilápia).',
        ]
    ),
    '25. Vender — callouts'
);

// ─── 27. DESPESA ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-nova-despesa.png" alt="Form despesa">
        <div class="legenda">Formulário de nova despesa</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-nova-despesa.png',
        'Wizard de despesa',
        'Wizard de registrar despesa · 4 passos',
        [
            { pos: 'top: 22%; left: 30%;' },
            { pos: 'top: 47%; left: 50%;' },
            { pos: 'top: 64%; left: 30%;', cor: 'azul' },
            { pos: 'top: 79%; left: 30%;', cor: 'laranja' },
            { pos: 'top: 89%; right: 12%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: O que pagou → Quanto foi → Conferência → Pronto.',
            '<strong>Descrição</strong> *: o que você comprou ou pagou (ex.: "Combustível no posto").',
            '<strong>Tipo de gasto</strong>: opcional. Se não tem na lista, pode <em>criar tipo novo</em>.',
            '<strong>Para quem pagou</strong>: opcional, pode <em>cadastrar fornecedor novo</em> daqui mesmo.',
            '<strong>Continuar</strong>: avança pro passo "Quanto foi" (valor + data).',
        ]
    ),
    '27. Despesa — callouts'
);

// ─── 28. RECEITA ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-nova-receita.png" alt="Form receita">
        <div class="legenda">Formulário de nova receita</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-nova-receita.png',
        'Wizard de receita',
        'Wizard de registrar receita · 4 passos',
        [
            { pos: 'top: 22%; left: 30%;' },
            { pos: 'top: 47%; left: 50%;' },
            { pos: 'top: 64%; left: 30%;', cor: 'azul' },
            { pos: 'top: 79%; left: 30%;', cor: 'laranja' },
            { pos: 'top: 89%; right: 12%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: De onde veio → Quanto foi → Conferência → Pronto.',
            '<strong>Descrição</strong> *: o que entrou (ex.: "Venda de leite — Laticínio Serrano").',
            '<strong>Tipo de receita</strong>: opcional (Venda de leite, Venda de animais, etc.).',
            '<strong>De quem recebeu</strong>: opcional, pode cadastrar cliente novo inline.',
            '<strong>Continuar</strong>: avança pro passo "Quanto foi".',
        ]
    ),
    '28. Receita — callouts'
);

// ─── 35. APLICAR PRODUTO (Aplicação) ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-aplicar-produto.png" alt="Wizard aplicar produto">
        <div class="legenda">Wizard de aplicação de produto</div>
    </div>`,
    printComCallouts(
        'screenshots/wizards/wizard-aplicar-produto.png',
        'Wizard de aplicação',
        'Wizard aplicar produto · cross-modules (talhão + estoque + financeiro)',
        [
            { pos: 'top: 22%; left: 22%;' },
            { pos: 'top: 38%; left: 50%;' },
            { pos: 'top: 60%; left: 50%;', cor: 'azul' },
            { pos: 'top: 75%; left: 50%;', cor: 'laranja' },
            { pos: 'top: 89%; right: 12%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: Talhão → Produto → Quantidade → Custo → Pronto (5 passos).',
            '<strong>Talhão</strong>: onde vai aplicar (escolhe da lista de talhões cadastrados).',
            '<strong>Tipo</strong>: herbicida, fungicida, inseticida, adubação.',
            '<strong>Produto</strong>: do estoque (sistema valida saldo disponível).',
            '<strong>Continuar</strong>: avança. No fim, gera evento + reduz estoque + cria despesa.',
        ]
    ),
    '35. Aplicação — callouts'
);

// ─── 40. MANUTENÇÃO ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-manutencao.png" alt="Wizard manutenção">
        <div class="legenda">Wizard de manutenção</div>
    </div>`,
    printComCallouts(
        'screenshots/wizards/wizard-manutencao.png',
        'Wizard de manutenção',
        'Wizard "Arrumar máquina" · OS de manutenção',
        [
            { pos: 'top: 22%; left: 22%;' },
            { pos: 'top: 47%; left: 30%;' },
            { pos: 'top: 47%; left: 50%;', cor: 'azul' },
            { pos: 'top: 89%; right: 12%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: A máquina → O serviço → Conferência → Pronto.',
            '<strong>Cadastrar máquina primeiro</strong>: aviso amarelo aparece se não há nenhum veículo. Use o atalho pra cadastrar.',
            '<strong>Cadastrar máquina</strong> (botão): vai pro form de Veículos sem perder o wizard.',
            '<strong>Continuar</strong>: depois de escolher a máquina, descreve o serviço (preventiva/corretiva).',
        ]
    ),
    '40. Manutenção — callouts'
);

// ─── 41. TAREFAS (form-nova-tarefa) ───
substituir(
    `    <h2>Criar tarefa</h2>
    <div class="caixa-print">
        <img src="screenshots/forms/form-nova-tarefa.png" alt="Form nova tarefa">
        <div class="legenda">Formulário de nova tarefa</div>
    </div>`,
    `    <h2>Criar tarefa</h2>
    ` + printComCallouts(
        'screenshots/forms/form-nova-tarefa.png',
        'Wizard de criar tarefa',
        'Wizard de criar tarefa · O que fazer → Sobre → Quem e quando → Conferência → Pronto',
        [
            { pos: 'top: 22%; left: 22%;' },
            { pos: 'top: 50%; left: 50%;' },
            { pos: 'top: 65%; left: 50%;', cor: 'azul' },
            { pos: 'top: 88%; right: 12%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: 5 passos. O passo atual fica destacado em verde.',
            '<strong>O que precisa ser feito?</strong> *: título curto da tarefa (ex.: "Capinar pasto 2", "Trocar óleo do trator").',
            '<strong>Detalhes</strong> (opcional): notas extras — como fazer, ferramentas, cuidados.',
            '<strong>Continuar</strong>: vai pro próximo passo onde escolhe responsável + prazo + prioridade.',
        ]
    ),
    '41. Tarefa — callouts'
);

// ─── 12. CADASTRAR ANIMAL ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-novo-animal-bovino.png" alt="Wizard de cadastro de animal">
        <div class="legenda">Wizard de cadastro · primeiro escolhe a espécie, depois preenche</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-novo-animal-bovino.png',
        'Wizard de cadastro de animal',
        'Wizard de cadastro · escolha a espécie primeiro',
        [
            { pos: 'top: 22%; left: 22%;' },
            { pos: 'top: 36%; left: 50%;' },
            { pos: 'top: 50%; left: 30%;', cor: 'azul' },
            { pos: 'top: 50%; left: 50%;', cor: 'laranja' },
            { pos: 'top: 50%; left: 70%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: Espécie → Identificação → Onde fica → Pronto (4 passos).',
            '<strong>Que tipo de animal?</strong>: a espécie define quais perguntas vêm depois (boi tem categoria leite/corte; peixe não tem isso).',
            '<strong>Cards das espécies</strong>: clique no card. Cada um mostra o nome + se é manejo individual ou lote.',
            '<strong>Bovino, Búfalo, Caprino…</strong>: animais individuais (cada um com brinco).',
            '<strong>Ave, Peixe</strong>: lote agregado (cadastra o lote inteiro com qtd cabeças).',
        ]
    ),
    '12. Cadastrar animal — callouts'
);

// ─── 22. LOTES ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-novo-lote.png" alt="Formulário de novo lote">
        <div class="legenda">Formulário de cadastro de lote</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-novo-lote.png',
        'Form de novo lote',
        'Cadastro de lote · agrupa animais por finalidade comum',
        [
            { pos: 'top: 27%; left: 30%;' },
            { pos: 'top: 38%; left: 50%;' },
            { pos: 'top: 50%; left: 30%;', cor: 'azul' },
            { pos: 'top: 70%; left: 30%;', cor: 'laranja' },
        ],
        [
            '<strong>Espécie do lote</strong> *: define se é lote de bovinos, ovinos, etc.',
            '<strong>Nome do lote</strong>: um nome descritivo (ex.: "Engorda Q1 2026", "Vacas leiteiras").',
            '<strong>Código curto</strong>: usado em listagens (ex.: "ENG-2026-Q1", "LEITE").',
            '<strong>Para quê serve este lote?</strong> (opcional): finalidade — ajuda a organizar relatórios.',
        ]
    ),
    '22. Lotes — callouts'
);

// ─── 43. PARCEIROS ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-novo-parceiro.png" alt="Form parceiro">
        <div class="legenda">Formulário de cadastro de parceiro</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-novo-parceiro.png',
        'Form de novo parceiro',
        'Cadastro de parceiro (cliente ou fornecedor)',
        [
            { pos: 'top: 31%; left: 30%;' },
            { pos: 'top: 31%; left: 60%;' },
            { pos: 'top: 50%; left: 30%;', cor: 'azul' },
            { pos: 'top: 75%; left: 30%;', cor: 'laranja' },
            { pos: 'top: 75%; left: 60%;', cor: 'verde' },
        ],
        [
            '<strong>Pessoa</strong>: jurídica (CNPJ) ou física (CPF).',
            '<strong>Relação comercial</strong>: cliente, fornecedor ou ambos.',
            '<strong>Razão social / Nome</strong>: nome formal (CNPJ) ou nome completo (CPF).',
            '<strong>E-mail</strong>: pra contato e envio de NF/comprovante.',
            '<strong>Telefone comercial</strong>: com máscara automática.',
        ]
    ),
    '43. Parceiros — callouts'
);

// ─── 44. USUÁRIOS / FUNCIONÁRIO ───
substituir(
    `    <div class="caixa-print">
        <img src="screenshots/forms/form-novo-funcionario.png" alt="Form funcionário">
        <div class="legenda">Formulário de cadastro de usuário</div>
    </div>`,
    printComCallouts(
        'screenshots/forms/form-novo-funcionario.png',
        'Wizard de cadastro de funcionário',
        'Wizard de cadastrar funcionário · vínculo + dados + função',
        [
            { pos: 'top: 22%; left: 22%;' },
            { pos: 'top: 47%; left: 30%;' },
            { pos: 'top: 47%; left: 65%;', cor: 'azul' },
            { pos: 'top: 65%; left: 30%;', cor: 'laranja' },
            { pos: 'top: 65%; left: 65%;', cor: 'verde' },
        ],
        [
            '<strong>Stepper</strong>: Vínculo → Identificação → Função → Pagamento → Pronto.',
            '<strong>CLT (carteira assinada)</strong>: vínculo formal com salário fixo.',
            '<strong>Prestador (PJ)</strong>: empresa contratada, NF mensal, sem vínculo CLT.',
            '<strong>Diarista</strong>: pago por dia trabalhado, sem vínculo permanente.',
            '<strong>Safrista (temporário)</strong>: contrato com início e fim definidos pra safra.',
        ]
    ),
    '44. Funcionário — callouts'
);

// ─── ADICIONAR PRINTS FALTANTES ────────────────

// ─── 5. TROCAR SENHA — adicionar print do avatar dropdown + form ───
substituir(
    `    <h2>Trocar minha senha (depois de já estar logado)</h2>
    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Clique no <strong>seu avatar</strong> (canto superior direito) → escolha "Alterar senha" no menu.</div>
    </div>`,
    `    <h2>Trocar minha senha (depois de já estar logado)</h2>

    <div class="caixa-print pequena">
        <img src="screenshots/desktop/avatar-dropdown.png" alt="Dropdown do avatar">
        <div class="legenda">Clique no avatar (topo direito) → opções aparecem</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Clique no <strong>seu avatar</strong> (canto superior direito) → escolha "Alterar senha" no menu.</div>
    </div>`,
    '5. Trocar senha — print avatar'
);

// Adicionar print do form-trocar-senha
substituir(
    `    <div class="passo">
        <div class="num">2</div>
        <div class="conteudo">Digite a senha atual e a senha nova (mesmas regras: 8+ chars, letras, números, símbolo).</div>
    </div>
    <div class="passo">
        <div class="num">3</div>
        <div class="conteudo">Clique em "Salvar".</div>
    </div>

    <div class="box box-info">
        <div class="titulo">ℹ️ A nova senha não pode ser igual à atual</div>`,
    `    <div class="passo">
        <div class="num">2</div>
        <div class="conteudo">Digite a senha atual e a senha nova (mesmas regras: 8+ chars, letras, números, símbolo).</div>
    </div>
    <div class="passo">
        <div class="num">3</div>
        <div class="conteudo">Clique em "Salvar".</div>
    </div>

    <div class="caixa-print pequena">
        <img src="screenshots/forms/form-trocar-senha.png" alt="Form de trocar senha">
        <div class="legenda">Tela de alterar senha</div>
    </div>

    <div class="box box-info">
        <div class="titulo">ℹ️ A nova senha não pode ser igual à atual</div>`,
    '5. Trocar senha — print form'
);

// ─── 9. TROCAR FAZENDA — print farm-selecionar ───
substituir(
    `    <p>Se sua empresa tem várias fazendas no sistema (Sede, Filial 1, Retiro), você precisa escolher qual delas vai operar. Cada fazenda tem seus próprios animais, contas, plantios, etc.</p>

    <div class="passo">
        <div class="num">1</div>`,
    `    <p>Se sua empresa tem várias fazendas no sistema (Sede, Filial 1, Retiro), você precisa escolher qual delas vai operar. Cada fazenda tem seus próprios animais, contas, plantios, etc.</p>

    <div class="caixa-print">
        <img src="screenshots/desktop/farm-selecionar.png" alt="Seletor de fazendas">
        <div class="legenda">Tela de seleção de fazenda · ativa fica destacada</div>
    </div>

    <div class="passo">
        <div class="num">1</div>`,
    '9. Trocar fazenda — print'
);

// ─── 17. ORDENHA — adicionar print ───
substituir(
    `    <p>A ordenha é registrada por animal, podendo informar até 3 ordenhas no mesmo dia (manhã, tarde, noite).</p>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → card <strong>"Registrar ordenha"</strong>.</div>
    </div>`,
    `    <p>A ordenha é registrada por animal, podendo informar até 3 ordenhas no mesmo dia (manhã, tarde, noite).</p>

    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-controle-leiteiro.png" alt="Wizard controle leiteiro">
        <div class="legenda">Wizard de controle leiteiro · uma vaca por linha, várias ordenhas/dia</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → card <strong>"Registrar ordenha"</strong>.</div>
    </div>`,
    '17. Ordenha — print'
);

// ─── 18. MOVER LOTE/LOCAL — adicionar print ───
substituir(
    `    <p>Quando você quer mover um animal pra outro lote (organização) ou pra outro pasto/curral (localização física), usa a ação "Mudar de lote".</p>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → card <strong>"Mudar de lote"</strong>.</div>
    </div>`,
    `    <p>Quando você quer mover um animal pra outro lote (organização) ou pra outro pasto/curral (localização física), usa a ação "Mudar de lote".</p>

    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-mover-lote.png" alt="Wizard mover de lote">
        <div class="legenda">Wizard "Mover animal de lote" · escolha animal/lote/pasto</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → card <strong>"Mudar de lote"</strong>.</div>
    </div>`,
    '18. Mover lote — print'
);

// ─── 19. REPRODUÇÃO — print exame-toque ───
substituir(
    `    <h2>Exame de toque (palpação)</h2>
    <p>Cerca de 60 dias após a cobertura, fazer o exame pra confirmar prenhez.</p>
    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → "Exame de toque".</div>
    </div>`,
    `    <h2>Exame de toque (palpação)</h2>
    <p>Cerca de 60 dias após a cobertura, fazer o exame pra confirmar prenhez.</p>

    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-exame-toque.png" alt="Wizard exame de toque">
        <div class="legenda">Wizard "Exame de toque" · positivo cria automaticamente tarefa de parto</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → "Exame de toque".</div>
    </div>`,
    '19. Reprodução — print exame-toque'
);

// ─── 20. MORTALIDADE — adicionar print ───
substituir(
    `    <p>Registrar mortalidade tira o animal do "ativo" e registra a baixa.</p>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → "Mortalidade" / "Registrar morte".</div>
    </div>`,
    `    <p>Registrar mortalidade tira o animal do "ativo" e registra a baixa.</p>

    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-mortalidade.png" alt="Wizard mortalidade">
        <div class="legenda">Wizard "Registrar morte do animal"</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Dashboard da espécie → "Mortalidade" / "Registrar morte".</div>
    </div>`,
    '20. Mortalidade — print'
);

// ─── 29. MARCAR PAGA — adicionar print ───
substituir(
    `    <p>Quando você cadastra uma despesa pendente (boleto que ainda vai vencer), ela aparece em amarelo. Quando você paga:</p>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Em <em>Financeiro → Transações</em>, encontre a despesa pendente.</div>
    </div>`,
    `    <p>Quando você cadastra uma despesa pendente (boleto que ainda vai vencer), ela aparece em amarelo. Quando você paga:</p>

    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · ícone "Quitar" verde aparece em cada despesa pendente</div>
    </div>

    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo">Em <em>Financeiro → Transações</em>, encontre a despesa pendente.</div>
    </div>`,
    '29. Marcar paga — print'
);

// ─── 30. ESTORNAR — print mesmo da lista ───
substituir(
    `    <p>Você pagou e depois descobriu que era valor errado, ou o boleto foi cancelado. Não dá pra simplesmente APAGAR a transação paga — o saldo do caixa ficaria errado. A solução é estornar.</p>

    <h2>Como estornar</h2>`,
    `    <p>Você pagou e depois descobriu que era valor errado, ou o boleto foi cancelado. Não dá pra simplesmente APAGAR a transação paga — o saldo do caixa ficaria errado. A solução é estornar.</p>

    <div class="caixa-print">
        <img src="screenshots/desktop/financeiro-transacoes-lista.png" alt="Lista de transações">
        <div class="legenda">Lista de transações · clique numa transação paga pra abrir e ver opção "Estornar"</div>
    </div>

    <h2>Como estornar</h2>`,
    '30. Estornar — print'
);

// ─── 37. RECEBER MERCADORIA — adicionar print ───
substituir(
    `    <p>Use o wizard "Receber mercadoria" — registra entrada no estoque E cria despesa no financeiro automaticamente.</p>

    <div class="passo">
        <div class="num">1</div>`,
    `    <p>Use o wizard "Receber mercadoria" — registra entrada no estoque E cria despesa no financeiro automaticamente.</p>

    <div class="caixa-print">
        <img src="screenshots/wizards/wizard-receber-mercadoria.png" alt="Wizard receber mercadoria">
        <div class="legenda">Wizard "Receber mercadoria" · entrada no estoque + despesa criada</div>
    </div>

    <div class="passo">
        <div class="num">1</div>`,
    '37. Receber mercadoria — print'
);

// ─── Salvar ───
fs.writeFileSync(FILE, html);
console.log('\n✅ Salvo em', FILE);
