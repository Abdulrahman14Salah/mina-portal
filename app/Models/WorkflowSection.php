<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowSection extends Model
{
    use HasFactory;

    protected $fillable = ['visa_type_id', 'name', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class)->orderBy('position');
    }
}
