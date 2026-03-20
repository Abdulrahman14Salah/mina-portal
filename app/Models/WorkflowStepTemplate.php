<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStepTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['visa_type_id', 'name', 'description', 'position', 'is_document_required'];

    protected $casts = [
        'is_document_required' => 'boolean',
        'position' => 'integer',
    ];

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    public function applicationTasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class);
    }
}
