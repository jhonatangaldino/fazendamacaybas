<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ImpersonationAudit — M5
 *
 * Registro persistente de cada sessão em que um MASTER assumiu o contexto
 * de um TENANT para operar/diagnosticar/dar suporte.
 *
 * Design (definido em M0):
 *   - Tabela `impersonation_audit` criada na migration
 *     2024_02_02_000002_create_impersonation_audit_table.php
 *   - SEM FK: preserva histórico mesmo após delete de user/tenant
 *   - `ended_at` NULLABLE: sessão ativa = NULL; sessão encerrada = timestamp
 *
 * Ciclo:
 *   - start (ImpersonationController::start) → cria registro com ended_at=null
 *   - exit  (ImpersonationController::exit)  → atualiza ended_at=now()
 */
class ImpersonationAudit extends Model
{
    protected $table = 'impersonation_audit';

    protected $fillable = [
        'impersonator_user_id',
        'tenant_id',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
