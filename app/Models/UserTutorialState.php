<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTutorialState extends Model
{
    protected $fillable = [
        'user_id', 'tutorial_key', 'status',
        'completed_at', 'dismissed_at', 'next_retry_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
