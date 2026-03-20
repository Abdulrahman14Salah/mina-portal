<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function visaApplications(): HasMany
    {
        return $this->hasMany(VisaApplication::class);
    }

    public function paymentStageConfigs(): HasMany
    {
        return $this->hasMany(PaymentStageConfig::class, 'visa_type_id')->orderBy('stage');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
