<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuUsage extends Model
{
    protected $table = 'menu_usage';

    protected $fillable = ['user_id', 'menu_key', 'hits', 'last_used_at'];

    protected $casts = [
        'hits' => 'integer',
        'last_used_at' => 'datetime',
    ];
}
