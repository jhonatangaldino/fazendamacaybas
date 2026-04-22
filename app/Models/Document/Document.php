<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'titulo', 'descricao', 'path', 'nome_arquivo', 'mime_type', 'size',
        'data_documento', 'data_vencimento', 'related_type', 'related_id',
        'is_confidential', 'tags', 'created_by',
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

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
