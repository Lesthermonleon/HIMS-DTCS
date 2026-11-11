<?php

namespace App\Models;

use App\Traits\HasStatusBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Surgery request submitted by a doctor for a patient.
 */
class SurgeryRequest extends Model
{
    use HasStatusBadge;

    public const STATUSES = [
        'Pending',
        'Scheduled',
        'In Progress',
        'Completed',
        'Cancelled',
    ];

    protected $fillable = [
        'request_no',
        'patient_id',
        'doctor_id',
        'procedure_name',
        'diagnosis',
        'urgency',
        'status',
        'notes',
        'anesthesia_type',
        'estimated_duration',
        'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(SurgerySchedule::class);
    }

    /**
     * Alias used by some legacy blade templates ($req->surgery_type).
     */
    public function getSurgeryTypeAttribute(): string
    {
        return (string) $this->procedure_name;
    }

    /**
     * Scope surgery requests visible to a given user according to SORS role rules.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['or-coordinator', 'admin', 'super_admin'])) {
            return $query;
        }

        if ($user->hasRole('doctor')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('doctor_id', $user->id)
                    ->orWhereHas('schedule.surgicalTeam', function (Builder $tq) use ($user) {
                        $tq->where('surgeon_id', $user->id)
                            ->orWhereHas('members', fn (Builder $mq) => $mq->where('user_id', $user->id));
                    });
            });
        }

        if ($user->hasRole('or-staff')) {
            return $query->whereHas('schedule.surgicalTeam.members', function (Builder $mq) use ($user) {
                $mq->where('user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
