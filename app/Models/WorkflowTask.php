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
    public const VALID_APPROVAL_MODES = ['auto', 'manual'];

    protected $fillable = ['workflow_section_id', 'name', 'description', 'type', 'position', 'approval_mode'];

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

            if ($task->approval_mode !== null && ! in_array($task->approval_mode, self::VALID_APPROVAL_MODES, true)) {
                throw new InvalidArgumentException(
                    "Invalid approval_mode '{$task->approval_mode}'. Must be one of: " . implode(', ', self::VALID_APPROVAL_MODES)
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
