<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeLookup extends Model
{
    protected $fillable = [
        'code', 'user_id', 'found_local', 'source',
        'http_status_off', 'http_status_upc',
        'nome_sugerido', 'marca_sugerida', 'nota_diagnostica', 'attempts_json',
    ];

    protected $casts = [
        'found_local' => 'boolean',
        'attempts_json' => 'array',
    ];
}
