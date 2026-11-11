<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyPosTransactionItem extends Model
{
    protected $table = 'pharmacy_pos_transaction_items';

    protected $fillable = [
        'pharmacy_pos_transaction_id',
        'prescription_item_id',
        'medicine_code',
        'medication_name',
        'generic_name',
        'brand_name',
        'category',
        'strength',
        'dosage_form',
        'unit',
        'lot_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'line_subtotal',
    ];

    protected $casts = [
        'expiry_date'   => 'date',
        'quantity'      => 'integer',
        'unit_price'    => 'decimal:2',
        'line_subtotal' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PharmacyPosTransaction::class, 'pharmacy_pos_transaction_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }
}

