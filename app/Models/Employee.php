<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;
    use BelongsToTenant, BelongsToFarm;

    protected $fillable = [
        'user_id', 'farm_id', 'nome', 'cpf', 'rg', 'data_nascimento', 'telefone', 'celular',
        'email', 'setor', 'funcao', 'salario', 'data_admissao', 'data_demissao',
        'cep', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'observacoes', 'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'data_admissao' => 'date',
        'data_demissao' => 'date',
        'salario' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
