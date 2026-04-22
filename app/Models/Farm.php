<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nome', 'cnpj', 'inscricao_estadual', 'cep', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'estado', 'telefone', 'email', 'area_total_ha', 'observacoes', 'is_active',
    ];

    protected $casts = [
        'area_total_ha' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
