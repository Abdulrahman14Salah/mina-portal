<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['application_task_id', 'task_question_id', 'answer'];

    public function applicationTask(): BelongsTo
    {
        return $this->belongsTo(ApplicationTask::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TaskQuestion::class, 'task_question_id');
    }
}
