<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationTask extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'position', 'name', 'description', 'status', 'reviewer_note', 'completed_at'];

    protected $casts = [
        'position' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepTemplate::class, 'workflow_step_template_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_task_id');
    }
}
