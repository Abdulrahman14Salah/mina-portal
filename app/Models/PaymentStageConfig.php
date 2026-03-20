<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStageConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'visa_type_id',
        'stage',
        'name',
        'amount',
        'currency',
    ];

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }
}
