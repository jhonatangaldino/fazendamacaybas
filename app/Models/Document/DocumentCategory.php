<?php

namespace App\Models\Document;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = ['nome', 'slug', 'cor', 'icon', 'is_active', 'tenant_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
