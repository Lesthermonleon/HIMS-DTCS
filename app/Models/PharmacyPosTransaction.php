<?php

namespace App\Models;

use App\Traits\HasStatusBadge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyPosTransaction extends Model
{
    use HasStatusBadge;

    protected $table = 'pharmacy_pos_transactions';

    protected $fillable = [
        'transaction_no',
        'sale_type',
        'patient_type',
        'prescription_id',
        'patient_id',
        'customer_name',
        'pharmacist_id',
        'terminal_id',
        'store_code',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'discount_reference',
        'discount_authorized_by',
        'vatable_sales',
        'vat_exempt_sales',
        'vat_rate',
        'vat_amount',
        'grand_total',
        'payment_method',
        'amount_tendered',
        'change_amount',
        'payment_reference',
        'status',
        'voided_by',
        'voided_at',
        'void_reason',
        'idempotency_key',
        'notes',
        'transacted_at',
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount_rate'    => 'decimal:4',
        'discount_amount'  => 'decimal:2',
        'vatable_sales'    => 'decimal:2',
        'vat_exempt_sales' => 'decimal:2',
        'vat_rate'         => 'decimal:4',
        'vat_amount'       => 'decimal:2',
        'grand_total'      => 'decimal:2',
        'amount_tendered'  => 'decimal:2',
        'change_amount'    => 'decimal:2',
        'voided_at'        => 'datetime',
        'transacted_at'    => 'datetime',
    ];

    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_VOIDED    = 'Voided';
    public const STATUS_REFUNDED  = 'Refunded';

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyPosTransactionItem::class, 'pharmacy_pos_transaction_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }

    public function discountAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_authorized_by');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}

