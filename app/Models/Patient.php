<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient model — the central entity for all clinical requests.
 */
class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_no',
        'user_id',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'blood_type',
        'address',
        'phone',
        'email',
        'emergency_contact_name',
        'emergency_contact_phone',
        'patient_type',
        'ward',
        'bed_number',
        'clinical_findings',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * Get the patient's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    // ──────────────────────────── Relationships ────────────────────────────

    public function labRequests(): HasMany
    {
        return $this->hasMany(LabRequest::class);
    }

    public function radiologyRequests(): HasMany
    {
        return $this->hasMany(RadiologyRequest::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function surgeryRequests(): HasMany
    {
        return $this->hasMany(SurgeryRequest::class);
    }

    public function dietRequests(): HasMany
    {
        return $this->hasMany(DietRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope patients that a specific doctor is authorized to view or order surgeries for.
     * Includes patients under this doctor's care or unassigned patients with no exclusive relationship to another doctor.
     */
    public function scopeAuthorizedForDoctor(Builder $query, User $doctor): Builder
    {
        if ($doctor->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($doctor) {
            // 1. Patients explicitly under this doctor's care
            $q->whereHas('appointments', fn (Builder $aq) => $aq->where('doctor_id', $doctor->id))
                ->orWhereHas('surgeryRequests', fn (Builder $sq) => $sq->where('doctor_id', $doctor->id))
                ->orWhereHas('labRequests', fn (Builder $lq) => $lq->where('doctor_id', $doctor->id))
                ->orWhereHas('radiologyRequests', fn (Builder $rq) => $rq->where('doctor_id', $doctor->id))
                ->orWhereHas('prescriptions', fn (Builder $pq) => $pq->where('doctor_id', $doctor->id))
                ->orWhereHas('dietRequests', fn (Builder $dq) => $dq->where('doctor_id', $doctor->id))
                // 2. Or newly registered / unassigned patients not yet assigned to another doctor
                ->orWhere(function (Builder $unassigned) {
                    $unassigned->whereDoesntHave('appointments')
                        ->whereDoesntHave('surgeryRequests')
                        ->whereDoesntHave('labRequests')
                        ->whereDoesntHave('radiologyRequests')
                        ->whereDoesntHave('prescriptions')
                        ->whereDoesntHave('dietRequests');
                });
        });
    }

    /**
     * Check whether a given doctor is authorized for this patient record.
     */
    public function isAuthorizedForDoctor(User $doctor): bool
    {
        if ($doctor->isAdmin()) {
            return true;
        }

        $hasOwnCareRelationship = $this->appointments()->where('doctor_id', $doctor->id)->exists()
            || $this->surgeryRequests()->where('doctor_id', $doctor->id)->exists()
            || $this->labRequests()->where('doctor_id', $doctor->id)->exists()
            || $this->radiologyRequests()->where('doctor_id', $doctor->id)->exists()
            || $this->prescriptions()->where('doctor_id', $doctor->id)->exists()
            || $this->dietRequests()->where('doctor_id', $doctor->id)->exists()
            || $this->surgeryRequests()
                ->whereHas('schedule.surgicalTeam', function (Builder $tq) use ($doctor) {
                    $tq->where('surgeon_id', $doctor->id)
                        ->orWhereHas('members', fn (Builder $mq) => $mq->where('user_id', $doctor->id));
                })->exists();

        if ($hasOwnCareRelationship) {
            return true;
        }

        // If the patient has clinical records with another doctor, restrict access
        $hasOtherDoctorRelationship = $this->appointments()->exists()
            || $this->surgeryRequests()->exists()
            || $this->labRequests()->exists()
            || $this->radiologyRequests()->exists()
            || $this->prescriptions()->exists()
            || $this->dietRequests()->exists();

        return ! $hasOtherDoctorRelationship;
    }
}
