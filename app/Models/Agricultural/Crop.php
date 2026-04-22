<?php

namespace App\Models\Agricultural;

use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    protected $fillable = ['nome', 'slug', 'variedade', 'ciclo_dias', 'unidade_producao', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
