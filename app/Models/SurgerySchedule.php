<?php

namespace App\Models;

use App\Traits\HasStatusBadge;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheduled surgery linking a surgery request, operating room, and surgical team.
 */
class SurgerySchedule extends Model
{
    use HasStatusBadge;

    public const STATUSES = [
        'Scheduled',
        'In Progress',
        'Completed',
        'Cancelled',
        'Postponed',
    ];

    public const READINESS_STATUSES = [
        'Pending Prep',
        'Preparing',
        'Ready',
    ];

    protected $fillable = [
        'surgery_request_id',
        'operating_room_id',
        'surgical_team_id',
        'scheduled_by',
        'scheduled_at',
        'duration_minutes',
        'status',
        'actual_start',
        'actual_end',
        'previous_readiness_status',
        'readiness_status',
        'readiness_notes',
        'readiness_updated_by',
        'readiness_updated_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_at'         => 'datetime',
        'actual_start'         => 'datetime',
        'actual_end'           => 'datetime',
        'readiness_updated_at' => 'datetime',
    ];

    public function surgeryRequest(): BelongsTo
    {
        return $this->belongsTo(SurgeryRequest::class);
    }

    public function operatingRoom(): BelongsTo
    {
        return $this->belongsTo(OperatingRoom::class);
    }

    public function surgicalTeam(): BelongsTo
    {
        return $this->belongsTo(SurgicalTeam::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function readinessUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'readiness_updated_by');
    }

    /**
     * Computed schedule number for display and print views.
     */
    public function getScheduleNoAttribute(): string
    {
        return 'SCH-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Scope schedules visible to the authenticated user according to SORS RBAC.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['or-coordinator', 'admin', 'super_admin'])) {
            return $query;
        }

        if ($user->hasRole('doctor')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereHas('surgeryRequest', fn (Builder $rq) => $rq->where('doctor_id', $user->id))
                    ->orWhereHas('surgicalTeam', function (Builder $tq) use ($user) {
                        $tq->where('surgeon_id', $user->id)
                            ->orWhereHas('members', fn (Builder $mq) => $mq->where('user_id', $user->id));
                    });
            });
        }

        if ($user->hasRole('or-staff')) {
            return $query->whereHas('surgicalTeam.members', function (Builder $mq) use ($user) {
                $mq->where('user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Check whether a given user (doctor or OR staff) is assigned to this schedule.
     */
    public function isAssignedToUser(User $user): bool
    {
        if ($this->surgeryRequest && (int) $this->surgeryRequest->doctor_id === (int) $user->id) {
            return true;
        }

        if (! $this->surgical_team_id) {
            return false;
        }

        $team = $this->surgicalTeam;
        if (! $team) {
            return false;
        }

        if ((int) $team->surgeon_id === (int) $user->id) {
            return true;
        }

        return $team->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Detect whether an Operating Room has an overlapping active schedule in the given window.
     */
    public static function findRoomConflict(
        int $operatingRoomId,
        $scheduledAt,
        int $durationMinutes,
        ?int $ignoreScheduleId = null
    ): ?self {
        $start = Carbon::parse($scheduledAt);
        $end   = $start->copy()->addMinutes(max(1, $durationMinutes));

        $candidates = self::with('surgeryRequest')
            ->where('operating_room_id', $operatingRoomId)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->when($ignoreScheduleId, fn (Builder $q) => $q->where('id', '!=', $ignoreScheduleId))
            ->get();

        foreach ($candidates as $existing) {
            $existingStart = Carbon::parse($existing->scheduled_at);
            $existingEnd   = $existingStart->copy()->addMinutes(max(1, (int) ($existing->duration_minutes ?: 60)));

            if ($existingStart->lt($end) && $start->lt($existingEnd)) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Detect whether a Surgical Team has an overlapping active schedule in the given window.
     */
    public static function findTeamConflict(
        ?int $surgicalTeamId,
        $scheduledAt,
        int $durationMinutes,
        ?int $ignoreScheduleId = null
    ): ?self {
        if (! $surgicalTeamId) {
            return null;
        }

        $start = Carbon::parse($scheduledAt);
        $end   = $start->copy()->addMinutes(max(1, $durationMinutes));

        $candidates = self::with('surgeryRequest')
            ->where('surgical_team_id', $surgicalTeamId)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->when($ignoreScheduleId, fn (Builder $q) => $q->where('id', '!=', $ignoreScheduleId))
            ->get();

        foreach ($candidates as $existing) {
            $existingStart = Carbon::parse($existing->scheduled_at);
            $existingEnd   = $existingStart->copy()->addMinutes(max(1, (int) ($existing->duration_minutes ?: 60)));

            if ($existingStart->lt($end) && $start->lt($existingEnd)) {
                return $existing;
            }
        }

        return null;
    }
}
