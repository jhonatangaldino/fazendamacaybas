<?php

namespace App\Models\Stock;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['farm_id', 'nome', 'localizacao', 'responsavel', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
