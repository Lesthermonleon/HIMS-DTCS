<?php

namespace App\Models;

use App\Traits\HasStatusBadge;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Therapeutic diet request submitted and managed by a doctor for an authorized patient.
 */
class DietRequest extends Model
{
    use HasStatusBadge;

    public const STATUSES = ['Pending', 'Active', 'Completed', 'Cancelled'];

    protected $fillable = [
        'request_no', 'patient_id', 'doctor_id', 'diet_type',
        'allergies', 'food_restrictions', 'clinical_notes', 'status', 'requested_at',
    ];

    protected $casts = ['requested_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function doctor(): BelongsTo  { return $this->belongsTo(User::class, 'doctor_id'); }
    public function dietPlan(): HasOne   { return $this->hasOne(DietPlan::class); }

    /**
     * Doctors only see their own requests; admin and super_admin have read-only monitoring visibility.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return $query;
        }

        if ($user->hasRole('doctor')) {
            return $query->where('doctor_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
