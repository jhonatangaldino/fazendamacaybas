<?php

namespace App\Models\Task;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = ['task_id', 'employee_id', 'user_id', 'assigned_at', 'completed_at'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
