<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\SurgeryRequest;
use App\Models\User;

/**
 * Policy governing SurgeryRequest access and clinical ownership in SORS.
 */
class SurgeryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'or-coordinator', 'admin', 'super_admin']);
    }

    public function view(User $user, SurgeryRequest $surgeryRequest): bool
    {
        if ($user->hasAnyRole(['or-coordinator', 'admin', 'super_admin'])) {
            return true;
        }

        if ($user->hasRole('doctor')) {
            if ((int) $surgeryRequest->doctor_id === (int) $user->id) {
                return true;
            }

            $schedule = $surgeryRequest->schedule;
            if ($schedule && $schedule->isAssignedToUser($user)) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Only clinical doctors may submit surgery requests, and only for authorized patients.
     */
    public function create(User $user, ?Patient $patient = null): bool
    {
        if (! $user->hasRole('doctor')) {
            return false;
        }

        if ($patient !== null && ! $patient->isAuthorizedForDoctor($user)) {
            return false;
        }

        return true;
    }

    /**
     * Only the originating doctor may update clinical details of a Pending surgery request.
     */
    public function update(User $user, SurgeryRequest $surgeryRequest): bool
    {
        if (! $user->hasRole('doctor')) {
            return false;
        }

        return (int) $surgeryRequest->doctor_id === (int) $user->id
            && $surgeryRequest->status === 'Pending';
    }

    /**
     * Only the originating doctor may delete their own Pending unscheduled surgery request.
     */
    public function delete(User $user, SurgeryRequest $surgeryRequest): bool
    {
        if (! $user->hasRole('doctor')) {
            return false;
        }

        return (int) $surgeryRequest->doctor_id === (int) $user->id
            && $surgeryRequest->status === 'Pending'
            && ! $surgeryRequest->schedule()->exists();
    }

    /**
     * Doctors may cancel their own Pending requests.
     * OR Coordinators may cancel Pending or Scheduled requests.
     * Admin and Super Admin administrative privileges do not automatically grant clinical cancellation authority.
     * In Progress and Completed requests cannot be cancelled.
     */
    public function cancel(User $user, SurgeryRequest $surgeryRequest): bool
    {
        if (in_array($surgeryRequest->status, ['In Progress', 'Completed', 'Cancelled'], true)) {
            return false;
        }

        if ($user->hasRole('or-coordinator')) {
            return in_array($surgeryRequest->status, ['Pending', 'Scheduled'], true);
        }

        if ($user->hasRole('doctor')) {
            return (int) $surgeryRequest->doctor_id === (int) $user->id
                && $surgeryRequest->status === 'Pending';
        }

        return false;
    }
}
