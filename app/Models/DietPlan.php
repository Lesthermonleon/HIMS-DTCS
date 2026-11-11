<?php

namespace App\Models;

use App\Traits\HasStatusBadge;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Therapeutic diet plan created and managed by the patient's physician (or historical dietitian record).
 */
class DietPlan extends Model
{
    use HasStatusBadge;

    public const STATUSES = ['Active', 'Revised', 'Completed'];

    protected $fillable = [
        'diet_request_id', 'dietitian_id', 'plan_details', 'assessment_notes', 'total_calories',
        'protein_grams', 'carb_grams', 'fat_grams',
        'start_date', 'end_date', 'status', 'notes',
        'updated_by', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'completed_at'  => 'datetime',
        'protein_grams' => 'decimal:2',
        'carb_grams'    => 'decimal:2',
        'fat_grams'     => 'decimal:2',
    ];

    public function dietRequest(): BelongsTo { return $this->belongsTo(DietRequest::class); }
    public function dietitian(): BelongsTo   { return $this->belongsTo(User::class, 'dietitian_id'); }
    public function createdBy(): BelongsTo   { return $this->belongsTo(User::class, 'dietitian_id'); }
    public function updatedBy(): BelongsTo   { return $this->belongsTo(User::class, 'updated_by'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function mealSchedules(): HasMany { return $this->hasMany(MealSchedule::class); }

    // ──────────────── View-friendly accessors (templates reference these names) ────────────────

    public function getPlanNoAttribute(): string
    {
        return 'DP-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getPatientAttribute(): ?Patient
    {
        return $this->dietRequest?->patient;
    }

    public function getDietTypeAttribute(): ?string
    {
        return $this->dietRequest?->diet_type;
    }

    public function getCaloriesAttribute()
    {
        return $this->total_calories;
    }

    public function getProteinAttribute()
    {
        return $this->protein_grams;
    }

    public function getCarbohydratesAttribute()
    {
        return $this->carb_grams;
    }

    public function getFatsAttribute()
    {
        return $this->fat_grams;
    }

    public function getInstructionsAttribute(): ?string
    {
        return $this->plan_details;
    }

    /**
     * Doctors only see plans for their own diet requests; admin and super_admin have read-only monitoring visibility.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return $query;
        }

        if ($user->hasRole('doctor')) {
            return $query->whereHas('dietRequest', fn (Builder $q) => $q->where('doctor_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
