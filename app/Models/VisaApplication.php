<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'visa_type_id',
        'assigned_reviewer_id',
        'status',
        'full_name',
        'email',
        'phone',
        'nationality',
        'country_of_residence',
        'job_title',
        'employment_type',
        'monthly_income',
        'adults_count',
        'children_count',
        'application_start_date',
        'notes',
        'agreed_to_terms',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'adults_count' => 'integer',
        'children_count' => 'integer',
        'agreed_to_terms' => 'boolean',
        'application_start_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (VisaApplication $application): void {
            $application->forceFill([
                'reference_number' => sprintf('APP-%05d', $application->id),
            ])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class, 'application_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'application_id');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }
}
