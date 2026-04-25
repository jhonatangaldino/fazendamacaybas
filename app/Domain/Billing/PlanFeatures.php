<?php

namespace App\Domain\Billing;

/**
 * PlanFeatures — catálogo FECHADO de funcionalidades comerciais dos planos.
 *
 * Cada chave aqui controla um módulo real do sistema. O master só pode
 * marcar/desmarcar essas opções no plano — nunca digitar texto livre.
 *
 * RESPONSABILIDADES:
 *   • Catálogo (chave → nome / descrição / tipo)
 *   • Map rota → feature (FeatureMap consumido pelo middleware EnforceFeature)
 *   • Validação (exists/sanitize) usada em PlanController
 *
 * TIPOS:
 *   modulo  — controla acesso a um módulo inteiro (rotas + menu)
 *   soft    — informativo (ex.: "Suporte prioritário"); aparece no plano
 *             mas não bloqueia rota nenhuma. Use para diferenciação comercial.
 *
 * ROTAS CORE (sempre disponíveis para qualquer tenant, mesmo plano vazio):
 *   admin.inicio (Hub), admin.dashboard, admin.faturas.*, admin.users.*,
 *   admin.roles.*, admin.fazenda.*, admin.pagamento-pendente, admin.feature-not-available.
 *   Estes não passam pelo EnforceFeature porque são CORE comercial — todo
 *   cliente precisa pagar e administrar usuários, independente do plano.
 */
class PlanFeatures
{
    /**
     * Catálogo: chave → metadados.
     * Ordenado conforme aparece no Form (ordem comercial razoável).
     */
    public const CATALOG = [
        'rebanho' => [
            'nome' => 'Rebanho',
            'descricao' => 'Animais, lotes, locais (pastos), eventos (peso, vacina, medicação, reprodução).',
            'tipo' => 'modulo',
        ],
        'agricola' => [
            'nome' => 'Agrícola',
            'descricao' => 'Talhões, plantios, colheitas, aplicações (defensivos, adubo).',
            'tipo' => 'modulo',
        ],
        'estoque' => [
            'nome' => 'Estoque',
            'descricao' => 'Itens, armazéns, movimentações, ajustes, recebimento.',
            'tipo' => 'modulo',
        ],
        'financeiro' => [
            'nome' => 'Financeiro',
            'descricao' => 'Contas a pagar/receber, transações, fluxo de caixa, contas bancárias.',
            'tipo' => 'modulo',
        ],
        'maquinas' => [
            'nome' => 'Máquinas',
            'descricao' => 'Veículos, implementos, manutenções, abastecimentos.',
            'tipo' => 'modulo',
        ],
        'funcionarios' => [
            'nome' => 'Funcionários',
            'descricao' => 'Cadastro de funcionários, cargos, contratos, vínculos.',
            'tipo' => 'modulo',
        ],
        'tarefas' => [
            'nome' => 'Tarefas',
            'descricao' => 'Atribuição, prazos, checklist, conclusão.',
            'tipo' => 'modulo',
        ],
        'documentos' => [
            'nome' => 'Documentos',
            'descricao' => 'Upload, categorização, anexos vinculados a entidades.',
            'tipo' => 'modulo',
        ],
        'parceiros' => [
            'nome' => 'Parceiros',
            'descricao' => 'Fornecedores, clientes/compradores, prestadores.',
            'tipo' => 'modulo',
        ],
        'relatorios' => [
            'nome' => 'Relatórios',
            'descricao' => 'Visão consolidada da operação por módulo e período.',
            'tipo' => 'modulo',
        ],
        'cms' => [
            'nome' => 'Landing / CMS',
            'descricao' => 'Página pública editável (banner, galeria, contato, mapa).',
            'tipo' => 'modulo',
        ],
        'suporte_prioritario' => [
            'nome' => 'Suporte prioritário',
            'descricao' => 'Atendimento humano em até 1 dia útil — sem bloqueio de rota.',
            'tipo' => 'soft',
        ],
    ];

    /**
     * Mapa rota nomeada → feature requerida.
     * Casa por PREFIXO: qualquer rota cujo nome comece com a chave aqui exige
     * a feature correspondente. Rotas sem match → CORE (não bloqueia).
     */
    public const ROUTE_TO_FEATURE = [
        'admin.rebanho.'                 => 'rebanho',
        'admin.agricola.'                => 'agricola',
        'admin.estoque.'                 => 'estoque',
        'admin.financeiro.'              => 'financeiro',
        'admin.maquinas.'                => 'maquinas',
        'admin.funcionarios.'            => 'funcionarios',
        'admin.tarefas.'                 => 'tarefas',
        'admin.documentos.'              => 'documentos',
        'admin.parceiros.'               => 'parceiros',
        'admin.relatorios.'              => 'relatorios',

        // Wizards (fluxos) que pertencem a um módulo específico
        'admin.fluxos.pesar-animal'      => 'rebanho',
        'admin.fluxos.evento-rebanho'    => 'rebanho',
        'admin.fluxos.cadastrar-animal'  => 'rebanho',
        'admin.fluxos.venda-animal'      => 'rebanho',
        'admin.fluxos.registrar-despesa' => 'financeiro',
        'admin.fluxos.registrar-receita' => 'financeiro',
        'admin.fluxos.receber-mercadoria'=> 'estoque',
        'admin.fluxos.saida-estoque'     => 'estoque',
        'admin.fluxos.ajustar-estoque'   => 'estoque',
        'admin.fluxos.aplicar-produto'   => 'agricola',
        'admin.fluxos.registrar-plantio' => 'agricola',
        'admin.fluxos.registrar-colheita'=> 'agricola',
        'admin.fluxos.arrumar-maquina'   => 'maquinas',
        'admin.fluxos.cadastrar-funcionario' => 'funcionarios',
        'admin.fluxos.criar-tarefa'      => 'tarefas',
        'admin.fluxos.anexar-documento'  => 'documentos',
    ];

    /** Todas as chaves do catálogo. */
    public static function allKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** Apenas as chaves que controlam módulo (filtra os tipo=soft). */
    public static function moduleKeys(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $meta) {
            if (($meta['tipo'] ?? null) === 'modulo') $out[] = $key;
        }
        return $out;
    }

    /** Verifica se a chave existe no catálogo. */
    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::CATALOG);
    }

    /** Filtra um array de chaves removendo as que não estão no catálogo. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_unique(array_filter($keys, fn ($k) => self::exists($k))));
    }

    /**
     * Retorna o catálogo formatado para o frontend (ordem do array).
     * Estrutura: [ ['key', 'nome', 'descricao', 'tipo'], ... ]
     */
    public static function catalogForFrontend(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $meta) {
            $out[] = [
                'key' => $key,
                'nome' => $meta['nome'],
                'descricao' => $meta['descricao'],
                'tipo' => $meta['tipo'],
            ];
        }
        return $out;
    }

    /**
     * Resolve a feature exigida por uma rota. Retorna null se for CORE.
     * Casa pelo prefixo mais longo primeiro (rotas wizard específicas têm
     * precedência sobre prefixos amplos).
     */
    public static function featureForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') return null;
        // Ordena por comprimento desc para que prefixos mais específicos batam primeiro
        $map = self::ROUTE_TO_FEATURE;
        uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($map as $prefix => $feature) {
            if (str_starts_with($routeName, $prefix)) return $feature;
        }
        return null;
    }
}
