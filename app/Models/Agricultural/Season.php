<?php

namespace App\Models\Agricultural;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['nome', 'data_inicio', 'data_fim', 'is_active'];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'is_active' => 'boolean',
    ];
}
