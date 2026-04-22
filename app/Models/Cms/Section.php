<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    protected $table = 'cms_sections';

    protected $fillable = [
        'page_id', 'type', 'nome', 'published_data', 'draft_data',
        'is_active', 'has_draft', 'order_column', 'published_at', 'updated_by',
    ];

    protected $casts = [
        'published_data' => 'array',
        'draft_data' => 'array',
        'is_active' => 'boolean',
        'has_draft' => 'boolean',
        'order_column' => 'integer',
        'published_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * Dado exibido no site público: se tiver rascunho publicado, usa published_data;
     * senão cai para draft_data (primeiro carregamento, antes de publicar).
     */
    public function dataForPublic(): array
    {
        return $this->published_data ?? $this->draft_data ?? [];
    }

    /**
     * Dado exibido no CMS admin: sempre o rascunho mais recente.
     */
    public function dataForEditor(): array
    {
        return $this->draft_data ?? $this->published_data ?? [];
    }

    public function publish(?int $userId = null): self
    {
        $this->update([
            'published_data' => $this->draft_data ?? $this->published_data,
            'has_draft' => false,
            'published_at' => now(),
            'updated_by' => $userId,
        ]);

        return $this;
    }
}
