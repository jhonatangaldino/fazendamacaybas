<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = ['nome', 'slug', 'cor', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }
}
