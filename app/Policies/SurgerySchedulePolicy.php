<?php

namespace App\Policies;

use App\Models\SurgerySchedule;
use App\Models\User;

/**
 * Policy governing Operating Room Schedule (SurgerySchedule) access, readiness, and status transitions.
 */
class SurgerySchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'or-coordinator', 'or-staff', 'admin', 'super_admin']);
    }

    public function view(User $user, SurgerySchedule $surgerySchedule): bool
    {
        if ($user->hasAnyRole(['or-coordinator', 'admin', 'super_admin'])) {
            return true;
        }

        if ($user->hasRole('doctor')) {
            return $surgerySchedule->isAssignedToUser($user);
        }

        if ($user->hasRole('or-staff')) {
            $team = $surgerySchedule->surgicalTeam;
            if (! $team) {
                return false;
            }

            return $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Only OR Coordinators may schedule operations and assign operating rooms.
     * Admin and Super Admin administrative privileges do not automatically grant OR scheduling authority.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('or-coordinator');
    }

    /**
     * Only OR Coordinators may modify or reschedule active OR schedules.
     * Completed or Cancelled schedules are immutable to preserve audit history.
     */
    public function update(User $user, SurgerySchedule $surgerySchedule): bool
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            return false;
        }

        return $user->hasRole('or-coordinator');
    }

    /**
     * Only OR Coordinators may remove an unstarted schedule.
     * Completed, Cancelled, and In Progress schedules cannot be deleted.
     */
    public function delete(User $user, SurgerySchedule $surgerySchedule): bool
    {
        if (! in_array($surgerySchedule->status, ['Scheduled', 'Postponed'], true)) {
            return false;
        }

        return $user->hasRole('or-coordinator');
    }

    /**
     * OR Coordinators and assigned OR Staff may update OR preparation/readiness status.
     * Doctors, Admin, and Super Admin must not update OR preparation or readiness on behalf of OR staff.
     */
    public function updateReadiness(User $user, SurgerySchedule $surgerySchedule): bool
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            return false;
        }

        if ($user->hasRole('or-coordinator')) {
            return true;
        }

        if ($user->hasRole('or-staff')) {
            $team = $surgerySchedule->surgicalTeam;
            return $team !== null && $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Record operational status transitions (start / complete).
     * Reserved for OR Coordinators by default; assigned OR Staff may execute only if explicitly granted
     * the 'sors.procedures.execute' permission by hospital governance.
     */
    public function updateStatus(User $user, SurgerySchedule $surgerySchedule): bool
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            return false;
        }

        if ($user->hasRole('or-coordinator')) {
            return true;
        }

        if ($user->hasRole('or-staff') && $user->hasPermission('sors.procedures.execute')) {
            $team = $surgerySchedule->surgicalTeam;
            return $team !== null && $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
