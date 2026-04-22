<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tipo', 'pessoa', 'nome', 'nome_fantasia', 'documento', 'inscricao_estadual',
        'email', 'telefone', 'celular', 'cep', 'endereco', 'numero', 'complemento',
        'bairro', 'cidade', 'estado', 'observacoes', 'is_active',
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
}
