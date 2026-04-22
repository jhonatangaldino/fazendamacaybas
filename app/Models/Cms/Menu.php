<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $table = 'cms_menus';

    protected $fillable = ['slug', 'nome', 'local', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')->orderBy('order_column');
    }

    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }
}
