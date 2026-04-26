<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    protected $fillable = [
        'key', 'titulo', 'rota', 'passos', 'permissions_required',
        'is_active', 'order_column',
    ];

    protected $casts = [
        'passos' => 'array',
        'permissions_required' => 'array',
        'is_active' => 'boolean',
        'order_column' => 'integer',
    ];

    public function scopeAtivos($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeParaRota($q, string $rota)
    {
        return $q->where('rota', $rota)->ativos()->orderBy('order_column');
    }
}
