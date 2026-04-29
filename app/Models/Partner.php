<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsAtividade;

class Partner extends Model
{
    use SoftDeletes, LogsAtividade;
    use BelongsToTenant;

    protected static function booted(): void
    {
        $invalidate = fn (Partner $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'parceiros');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'tipo', 'pessoa', 'nome', 'nome_fantasia', 'documento', 'inscricao_estadual',
        'email', 'telefone', 'celular', 'cep', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'estado', 'observacoes', 'is_active',
        'tenant_id',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeFornecedores($q)
    {
        return $q->whereIn('tipo', ['fornecedor', 'ambos']);
    }

    public function scopeClientes($q)
    {
        return $q->whereIn('tipo', ['cliente', 'ambos']);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
