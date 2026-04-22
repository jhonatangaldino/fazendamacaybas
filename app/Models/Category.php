<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['parent_id', 'tipo', 'nome', 'slug', 'cor', 'icon', 'order_column', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'order_column' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function scopeTipo($q, string $tipo)
    {
        return $q->where('tipo', $tipo);
    }
}
