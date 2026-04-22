<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $table = 'cms_menu_items';

    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'url', 'target', 'icon', 'order_column', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_column' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order_column');
    }
}
