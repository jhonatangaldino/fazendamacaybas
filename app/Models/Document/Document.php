<?php

namespace App\Models\Document;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;
    use BelongsToTenant, BelongsToFarm;

    protected static function booted(): void
    {
        $invalidate = fn (Document $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'documentos');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'category_id', 'titulo', 'descricao', 'path', 'nome_arquivo', 'mime_type', 'size',
        'data_documento', 'data_vencimento', 'related_type', 'related_id',
        'is_confidential', 'tags', 'created_by',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'data_documento' => 'date',
        'data_vencimento' => 'date',
        'is_confidential' => 'boolean',
        'tags' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
