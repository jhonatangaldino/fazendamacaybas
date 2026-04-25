<?php

namespace App\Models;

use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Centro de custo — catálogo do tenant. Cross-farm intencional (cada tenant
 * organiza seus centros de custo livremente; uma rubrica como "Manutenção"
 * pode aglutinar gastos de várias fazendas dele).
 *
 * Isolamento por TENANT (não por farm) via `BelongsToTenant`. Scope global
 * filtra `WHERE tenant_id = current_tenant_id`, blindando contra leitura
 * cross-tenant. Códigos são únicos POR tenant (composite unique no DB).
 */
class CostCenter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'codigo', 'nome', 'descricao', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
