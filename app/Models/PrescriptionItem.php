<?php

namespace App\Models;

use App\Traits\HasStatusBadge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Individual medication line item within a prescription.
 */
class PrescriptionItem extends Model
{
    use HasStatusBadge;

    protected $fillable = [
        'prescription_id', 'medication_name', 'dosage', 'route',
        'frequency', 'duration', 'quantity', 'instructions', 'status',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function dispensingRecords(): HasMany
    {
        return $this->hasMany(DispensingRecord::class);
    }

    public function getDispensedQuantityAttribute(): int
    {
        if ($this->relationLoaded('dispensingRecords')) {
            return (int) $this->dispensingRecords->sum('quantity_dispensed');
        }

        return (int) $this->dispensingRecords()->sum('quantity_dispensed');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - $this->dispensed_quantity);
    }
}

