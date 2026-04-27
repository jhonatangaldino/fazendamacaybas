<?php

namespace App\Support;

/**
 * PermissionLabels — gera labels legíveis em pt-BR para chaves técnicas
 * de permissões do Spatie.
 *
 * Convenções de nomes (já em uso no banco):
 *   operational.{modulo}.{acao}                   ex.: operational.agricola.view
 *   operational.{modulo}.{entidade}.{acao}        ex.: operational.agricola.aplicacoes.create
 *   platform.{recurso}.{acao}                     ex.: platform.tenants.manage
 *
 * Ações comuns: view, create, update, delete, manage, approve, reset_password,
 *                publish, start, stop
 */
class PermissionLabels
{
    private const MODULOS = [
        'agricola' => 'Agrícola',
        'rebanho' => 'Rebanho',
        'estoque' => 'Estoque',
        'financeiro' => 'Financeiro',
        'maquinas' => 'Máquinas',
        'funcionarios' => 'Funcionários',
        'documentos' => 'Documentos',
        'relatorios' => 'Relatórios',
        'parceiros' => 'Parceiros',
        'usuarios' => 'Usuários',
        'dashboard' => 'Painel',
        'fazendas' => 'Fazendas',
        // Platform (master)
        'tenants' => 'Clientes (master)',
        'plans' => 'Planos (master)',
        'billing' => 'Cobrança (master)',
        'cms' => 'Site (master)',
        'roles' => 'Perfis e permissões',
        'settings' => 'Configurações',
        'users' => 'Usuários da plataforma',
        'farms' => 'Fazendas (master)',
        'impersonation' => 'Acessar como cliente',
    ];

    private const ENTIDADES = [
        // Agrícola
        'aplicacoes' => 'Aplicações',
        'colheitas' => 'Colheitas',
        'plantios' => 'Plantios',
        'talhoes' => 'Talhões',
        // Rebanho
        'animais' => 'Animais',
        'lotes' => 'Lotes',
        'eventos' => 'Eventos (vacina, peso, mortes)',
        'locais' => 'Locais (pastos)',
        // Estoque
        'armazens' => 'Armazéns',
        'itens' => 'Itens',
        'movimentos' => 'Movimentações (entrada/saída)',
        // Financeiro
        'contas' => 'Contas bancárias',
        'transacoes' => 'Transações',
        'recorrencias' => 'Recorrências',
        'relatorios' => 'Relatórios',
        // Máquinas
        'veiculos' => 'Máquinas/Veículos',
        'manutencoes' => 'Manutenções',
        // Funcionários
        'cadastro' => 'Cadastro',
        'tarefas' => 'Tarefas',
    ];

    private const ACOES = [
        'view' => 'Visualizar',
        'create' => 'Cadastrar',
        'update' => 'Editar',
        'delete' => 'Excluir',
        'manage' => 'Gerenciar (tudo)',
        'approve' => 'Aprovar',
        'reset_password' => 'Resetar senha',
        'publish' => 'Publicar',
        'start' => 'Iniciar',
        'stop' => 'Encerrar',
    ];

    /**
     * Label completo do permission. Ex.:
     *   operational.agricola.aplicacoes.create  →  "Aplicações · Cadastrar"
     *   operational.agricola.view               →  "Acesso ao módulo · Visualizar"
     *   platform.tenants.manage                 →  "Gerenciar (tudo)"
     */
    public static function label(string $name): string
    {
        $parts = explode('.', $name);

        // platform.{recurso}.{acao}
        if (($parts[0] ?? '') === 'platform') {
            $acao = end($parts);
            return self::ACOES[$acao] ?? ucfirst($acao);
        }

        // operational.{modulo}.{entidade}.{acao}
        if (count($parts) === 4 && $parts[0] === 'operational') {
            $entidade = self::ENTIDADES[$parts[2]] ?? ucfirst($parts[2]);
            $acao = self::ACOES[$parts[3]] ?? ucfirst($parts[3]);
            return $entidade . ' · ' . $acao;
        }

        // operational.{modulo}.{acao}
        if (count($parts) === 3 && $parts[0] === 'operational') {
            $acao = self::ACOES[$parts[2]] ?? ucfirst($parts[2]);
            // Ações no nível do módulo são "permissões agregadoras"
            // que controlam acesso ao módulo inteiro
            return 'Acesso ao módulo · ' . $acao;
        }

        // Fallback: nome técnico mesmo (não deve acontecer)
        return $name;
    }

    /**
     * Nome amigável do módulo. Ex.: 'agricola' → 'Agrícola'.
     */
    public static function moduleName(string $module): string
    {
        return self::MODULOS[$module] ?? ucfirst($module);
    }

    /**
     * Descrição contextual curta da permissão.
     * Útil em tooltips para esclarecer dúvidas como "o que é 'Aprovar'?"
     */
    public static function description(string $name): ?string
    {
        $parts = explode('.', $name);
        $acao = end($parts);

        return match ($acao) {
            'view' => 'Permite ver as informações desta área.',
            'create' => 'Permite cadastrar novos registros.',
            'update' => 'Permite editar registros existentes.',
            'delete' => 'Permite excluir registros.',
            'manage' => 'Permite TUDO nesta área (visualizar, cadastrar, editar e excluir).',
            'approve' => 'Permite aprovar lançamentos pendentes.',
            'reset_password' => 'Permite resetar a senha de outros usuários.',
            'publish' => 'Permite publicar conteúdo (sair do rascunho).',
            default => null,
        };
    }
}
