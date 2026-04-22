<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = ['codigo', 'nome', 'descricao', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
