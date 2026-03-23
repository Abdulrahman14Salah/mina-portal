<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['application_id', 'application_task_id', 'uploaded_by', 'source_type', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'size', 'archived_at', 'expires_at'];

    protected $casts = [
        'size' => 'integer',
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'application_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ApplicationTask::class, 'application_task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
