<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'patient_id',
        'appointment_id',
        'description',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'payment_method',
        'payment_reference',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'balance'      => 'decimal:2',
        'paid_at'      => 'datetime',
    ];

    public const STATUS_UNPAID   = 'Unpaid';
    public const STATUS_PARTIAL  = 'Partially Paid';
    public const STATUS_PAID     = 'Paid';
    public const STATUS_VOIDED   = 'Voided';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
