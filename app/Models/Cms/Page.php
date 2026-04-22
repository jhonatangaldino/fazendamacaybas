<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = [
        'slug', 'titulo', 'meta_title', 'meta_description', 'meta_keywords',
        'og_image_path', 'is_published', 'published_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'page_id')->orderBy('order_column');
    }

    public function activeSections(): HasMany
    {
        return $this->sections()->where('is_active', true);
    }
}
