<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'workflow_step_template_id',
        'workflow_task_id',
        'position',
        'name',
        'description',
        'type',
        'approval_mode',
        'status',
        'reviewer_note',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'completed_at',
    ];

    protected $casts = [
        'position'    => 'integer',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepTemplate::class, 'workflow_step_template_id');
    }

    public function workflowTask(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_task_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TaskAnswer::class);
    }
}
