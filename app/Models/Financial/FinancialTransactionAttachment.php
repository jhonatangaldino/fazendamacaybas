<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransactionAttachment extends Model
{
    protected $fillable = ['transaction_id', 'nome', 'path', 'mime_type', 'size'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }
}
