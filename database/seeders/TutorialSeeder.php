<?php

namespace Database\Seeders;

use App\Models\Tutorial;
use Illuminate\Database\Seeder;

/**
 * 8 tutoriais iniciais cobrindo as principais jornadas do dia a dia
 * + ações que o usuário possa não descobrir sozinho.
 *
 * Idempotente: usa updateOrCreate por key.
 */
class TutorialSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $t) {
            Tutorial::updateOrCreate(
                ['key' => $t['key']],
                [
                    'titulo' => $t['titulo'],
                    'rota' => $t['rota'],
                    'passos' => $t['passos'],
                    'permissions_required' => $t['permissions_required'] ?? null,
                    'is_active' => true,
                    'order_column' => $t['order_column'] ?? 0,
                ]
            );
        }
    }

    private function catalog(): array
    {
        return [
            [
                'key' => 'hub.boas-vindas',
                'titulo' => 'Boas-vindas — tela de Início',
                'rota' => '/admin/inicio',
                'order_column' => 1,
                'passos' => [
                    ['titulo' => 'Esta é a sua tela de Início', 'descricao' => 'Tudo que você faz no dia a dia da fazenda começa aqui. Os atalhos estão agrupados por frequência: o que você faz Todo dia, Toda semana, etc.'],
                    ['titulo' => 'Ações com ícones', 'descricao' => 'Cada ícone representa uma ação. Balança = pesar; seringa = vacina; folha = defensivo. Toque para abrir o passo a passo.'],
                    ['titulo' => 'Etiquetas claras', 'descricao' => '"PASSO A PASSO" = assistente com várias etapas guiadas. "AÇÃO RÁPIDA" = um toque faz tudo (ex.: marcar uma tarefa como concluída).'],
                    ['titulo' => 'Pronto!', 'descricao' => 'Comece pelo atalho que mais faz sentido pro seu dia. Tudo que você cadastrar vai aparecer no Painel (com totais e gráficos).'],
                ],
            ],
            [
                'key' => 'dashboard.primeiros-passos',
                'titulo' => 'Painel de números',
                'rota' => '/admin/dashboard',
                'order_column' => 2,
                'passos' => [
                    ['titulo' => 'Visão geral', 'descricao' => 'Aqui você vê os totais: receitas e despesas do mês, saldo, tarefas pendentes, alertas de estoque.'],
                    ['titulo' => 'Comece pela tela de Início', 'descricao' => 'Se ainda não cadastrou movimentos, volte para Início e use os atalhos de ação. Os números aparecem assim que você registra.'],
                ],
            ],
            [
                'key' => 'rebanho.lista',
                'titulo' => 'Lista de animais',
                'rota' => '/admin/rebanho/animais',
                'order_column' => 3,
                'permissions_required' => ['operational.rebanho.view'],
                'passos' => [
                    ['titulo' => 'Cadastro individual', 'descricao' => 'Cada animal tem brinco, peso, lote (grupo lógico) e local (pasto onde fica fisicamente).'],
                    ['titulo' => 'Histórico incremental', 'descricao' => 'Pesagens, vacinas e eventos ficam guardados no animal — você consegue ver o ganho de peso ao longo do tempo.'],
                    ['titulo' => 'Vender animal', 'descricao' => 'Use o botão verde "Vender animal" para abrir o assistente. Você seleciona vários, define unidade (arroba, kg, cabeça) e finaliza com 1 nota.'],
                ],
            ],
            [
                'key' => 'lotes-vs-locais',
                'titulo' => 'Lote ≠ Local',
                'rota' => '/admin/rebanho/lotes',
                'order_column' => 4,
                'permissions_required' => ['operational.rebanho.view'],
                'passos' => [
                    ['titulo' => 'Lote é um GRUPO LÓGICO', 'descricao' => 'Exemplo: "Vacas leiteiras", "Engorda 2026", "Descarte". Não muda quando o animal anda.'],
                    ['titulo' => 'Local é um LUGAR FÍSICO', 'descricao' => 'Pasto, piquete, curral, tanque. Um mesmo lote pode mudar de local (rotação de pasto) sem perder identidade.'],
                ],
            ],
            [
                'key' => 'financeiro.hub',
                'titulo' => 'Financeiro — começo',
                'rota' => '/admin/financeiro',
                'order_column' => 5,
                'permissions_required' => ['operational.financeiro.view'],
                'passos' => [
                    ['titulo' => 'Indicadores do topo', 'descricao' => 'Saldo total nas suas contas, receitas/despesas do mês, contas atrasadas (em vermelho).'],
                    ['titulo' => 'Atalhos rápidos', 'descricao' => 'Registrar despesa, Registrar receita, Pagar contas — todos abrem assistente guiado.'],
                    ['titulo' => 'Cadastre uma conta primeiro', 'descricao' => 'Antes de lançar movimentos, cadastre pelo menos 1 conta financeira (banco ou caixa). O sistema avisa.'],
                ],
            ],
            [
                'key' => 'wizard.despesa',
                'titulo' => 'Como registrar uma despesa',
                'rota' => '/admin/fluxos/registrar-despesa',
                'order_column' => 6,
                'permissions_required' => ['operational.financeiro.transacoes.create'],
                'passos' => [
                    ['titulo' => 'Passo 1 — O que pagou', 'descricao' => 'Descreva (ex.: "Adubo NPK") e escolha conta + categoria. Se não tiver categoria, crie na hora pelo botão "Nova".'],
                    ['titulo' => 'Passo 2 — Quanto foi', 'descricao' => 'Valor em reais. O sistema aplica máscara automática.'],
                    ['titulo' => 'Passo 3 — Conferência', 'descricao' => 'Revise. Pode voltar e ajustar antes de salvar.'],
                    ['titulo' => 'Passo 4 — Pronto!', 'descricao' => 'Despesa entra no fluxo de caixa. Aparece no Painel e na lista de Transações.'],
                ],
            ],
            [
                'key' => 'tarefas.lista',
                'titulo' => 'Lista de tarefas',
                'rota' => '/admin/tarefas',
                'order_column' => 7,
                'permissions_required' => ['operational.funcionarios.tarefas.view'],
                'passos' => [
                    ['titulo' => 'Números por estado', 'descricao' => 'Atrasadas (vermelho), Para hoje (amarelo), Pendentes, Feitas hoje (verde). Toque em qualquer um para filtrar.'],
                    ['titulo' => 'Criar tarefa', 'descricao' => 'Use "+ Nova tarefa" ou o atalho "Criar tarefa" na tela de Início para abrir o assistente guiado.'],
                    ['titulo' => 'Concluir', 'descricao' => 'Marque como concluída diretamente da lista — 1 toque. Não precisa abrir.'],
                ],
            ],
            [
                'key' => 'fazenda.trocar',
                'titulo' => 'Trocar de fazenda',
                'rota' => '/admin/fazenda/selecionar',
                'order_column' => 8,
                'passos' => [
                    ['titulo' => 'Quando aparece esta tela', 'descricao' => 'Quando seu acesso tem mais de 1 fazenda. Você escolhe qual operar agora.'],
                    ['titulo' => 'Isolamento total', 'descricao' => 'Cada fazenda tem seus próprios animais, contas, transações. Trocar muda 100% do contexto.'],
                    ['titulo' => 'Volta pra tela de Início', 'descricao' => 'Após trocar, você é levado para a tela de Início da nova fazenda — começa fresco, com os ícones contextuais.'],
                ],
            ],
        ];
    }
}
