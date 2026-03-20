<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'stage',
        'name',
        'amount',
        'currency',
        'status',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'application_id');
    }
}
