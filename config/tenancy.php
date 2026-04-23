<?php

/**
 * Configuração da camada de tenancy (multi-tenant).
 *
 * Nesta release (R1.5): apenas DETECTOR. Nada é filtrado, bloqueado ou forçado.
 *
 * As chaves abaixo são consumidas pelo trait BelongsToTenant e pelos serviços
 * de tenancy (quando existirem em R2+). O enforcement de verdade só é ligado
 * em release posterior — 'enforcement.enabled' fica propositalmente false aqui.
 */
return [

    /*
    |---------------------------------------------------------------------------
    | Detector de uso de tenancy (R1.5 — observabilidade)
    |---------------------------------------------------------------------------
    |
    | Quando 'enabled' é true, o trait BelongsToTenant loga warnings em queries
    | onde o contexto de tenant está ausente ou divergente do dado carregado.
    | NÃO filtra, NÃO bloqueia, NÃO lança exceções.
    |
    | 'channel' define o canal de log para onde os warnings vão. O canal
    | 'tenancy' está declarado em config/logging.php e escreve em
    | storage/logs/tenancy-detector.log — isolado do laravel.log para não
    | poluir o diagnóstico normal.
    */
    'detector' => [
        'enabled' => (bool) env('TENANCY_DETECTOR_ENABLED', true),
        'channel' => env('TENANCY_DETECTOR_CHANNEL', 'tenancy'),

        // Se true, ignora warnings quando o user autenticado é master global
        // (tenant_id = NULL). Master global opera em contexto platform-wide.
        'skip_master_global' => true,

        // Se true, ignora execução em CLI (migrations, seeders, artisan, queue).
        // Recomendado true — contexto de tenancy ainda não existe em jobs na R1.
        'skip_console' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | Enforcement (R2 — NÃO aplicado ainda)
    |---------------------------------------------------------------------------
    | Reserva estrutural. Continua false até a R2 entrar em produção.
    */
    'enforcement' => [
        'enabled' => false,
        'scope_global' => false,    // aplicar WHERE tenant_id = ? em todas queries
        'force_on_creating' => false, // forçar model->tenant_id = app('tenant_id')
    ],
];
