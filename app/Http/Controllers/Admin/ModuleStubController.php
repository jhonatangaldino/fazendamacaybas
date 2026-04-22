<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

/**
 * Controller genérico para módulos que terão CRUD completo em sprints futuros.
 * Exibe uma tela de "Módulo preparado" com o que já está pronto (migrations, models) e o que vem a seguir.
 * Todas as permissões e navegação já funcionam; só a UI do CRUD será expandida.
 */
class ModuleStubController extends Controller
{
    public function agricola() { return $this->stub('Produção agrícola', 'agricola', [
        'Cadastro de talhões / áreas',
        'Plantios e safras',
        'Colheitas e produtividade',
        'Aplicações de insumos',
    ]); }

    public function estoque() { return $this->stub('Estoque e almoxarifado', 'estoque', [
        'Cadastro de itens (insumos, medicamentos, ração, peças)',
        'Entradas e saídas',
        'Transferências entre armazéns',
        'Alertas de estoque mínimo',
    ]); }

    public function maquinas() { return $this->stub('Máquinas e veículos', 'maquinas', [
        'Cadastro de veículos e implementos',
        'Ordens de manutenção (preventiva e corretiva)',
        'Controle de horímetro/quilometragem',
    ]); }

    public function funcionarios() { return $this->stub('Funcionários', 'funcionarios', [
        'Cadastro completo com CPF, contratos e histórico',
        'Setores e funções',
        'Controle de admissão e demissão',
    ]); }

    public function tarefas() { return $this->stub('Tarefas', 'funcionarios.tarefas', [
        'Criação e atribuição de tarefas',
        'Checklists com itens marcáveis',
        'Acompanhamento por setor/funcionário',
    ]); }

    public function documentos() { return $this->stub('Documentos', 'documentos', [
        'Upload e categorização',
        'Contratos, NFs, comprovantes, documentos sanitários',
        'Busca, filtros e controle de vencimento',
    ]); }

    public function relatorios() { return $this->stub('Relatórios', 'relatorios', [
        'Relatórios financeiros por período',
        'Relatórios de rebanho',
        'Produtividade agrícola',
        'Giro de estoque',
        'Exportação em PDF/Excel',
    ]); }

    public function cmsSettings() { return Inertia::render('Admin/Cms/Settings'); }

    protected function stub(string $title, string $module, array $next)
    {
        return Inertia::render('Admin/Stub', [
            'title' => $title,
            'module' => $module,
            'next' => $next,
        ]);
    }
}
