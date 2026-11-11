<?php

namespace App\Models;

use App\Traits\HasStatusBadge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prescription issued by a doctor for a patient.
 */
class Prescription extends Model
{
    use HasStatusBadge;

    protected $fillable = [
        'prescription_no', 'patient_id', 'doctor_id', 'status',
        'notes', 'diagnosis', 'prescribed_at', 'verified_by', 'verified_at',
        'verification_notes', 'rejection_reason', 'rejected_by', 'rejected_at',
    ];

    protected $casts = [
        'prescribed_at' => 'datetime',
        'verified_at'   => 'datetime',
        'rejected_at'   => 'datetime',
    ];

    public function patient(): BelongsTo        { return $this->belongsTo(Patient::class); }
    public function doctor(): BelongsTo         { return $this->belongsTo(User::class, 'doctor_id'); }
    public function verifiedBy(): BelongsTo     { return $this->belongsTo(User::class, 'verified_by'); }
    public function rejectedBy(): BelongsTo     { return $this->belongsTo(User::class, 'rejected_by'); }
    public function items(): HasMany            { return $this->hasMany(PrescriptionItem::class); }
    public function posTransactions(): HasMany  { return $this->hasMany(PharmacyPosTransaction::class); }

    
}

