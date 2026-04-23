<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'nome', 'cnpj', 'inscricao_estadual', 'cep', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'estado', 'telefone', 'email', 'area_total_ha', 'observacoes', 'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'area_total_ha' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
