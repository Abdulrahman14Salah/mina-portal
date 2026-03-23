<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class WorkflowTask extends Model
{
    use HasFactory;

    public const VALID_TYPES = ['upload', 'question', 'payment', 'info'];

    protected $fillable = ['workflow_section_id', 'name', 'description', 'type', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! in_array($task->type, self::VALID_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Invalid workflow task type '{$task->type}'. Must be one of: " . implode(', ', self::VALID_TYPES)
                );
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkflowSection::class, 'workflow_section_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TaskQuestion::class)->orderBy('position');
    }
}
