<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_task_id', 'prompt', 'required', 'position'];

    protected $casts = [
        'required' => 'boolean',
        'position' => 'integer',
    ];

    public function workflowTask(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TaskAnswer::class);
    }
}
