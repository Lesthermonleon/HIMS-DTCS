<?php

namespace App\Policies;

use App\Models\DietRequest;
use App\Models\Patient;
use App\Models\User;

/**
 * Policy governing DietRequest access in the Doctor-Managed DNMS workflow.
 *
 * Doctors own clinical diet orders for their authorized patients.
 * Admin / Super Admin have read-only monitoring access and no clinical mutation authority.
 */
class DietRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'admin', 'super_admin']);
    }

    public function view(User $user, DietRequest $dietRequest): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        if (! $user->hasRole('doctor')) {
            return false;
        }

        return (int) $dietRequest->doctor_id === (int) $user->id
            && ($dietRequest->patient === null || $dietRequest->patient->isAuthorizedForDoctor($user));
    }

    /**
     * Only doctors may order therapeutic diets, and only for patients they are authorized for.
     */
    public function create(User $user, ?Patient $patient = null): bool
    {
        if (! $user->hasRole('doctor')) {
            return false;
        }

        return $patient === null || $patient->isAuthorizedForDoctor($user);
    }

    /**
     * Only the originating doctor may edit their own Pending request for an authorized patient.
     */
    public function update(User $user, DietRequest $dietRequest): bool
    {
        return $user->hasRole('doctor')
            && (int) $dietRequest->doctor_id === (int) $user->id
            && $dietRequest->status === 'Pending'
            && ($dietRequest->patient === null || $dietRequest->patient->isAuthorizedForDoctor($user));
    }

    /**
     * Only the originating doctor may cancel their own Pending request that has no diet plan yet.
     */
    public function delete(User $user, DietRequest $dietRequest): bool
    {
        return $this->update($user, $dietRequest) && ! $dietRequest->dietPlan()->exists();
    }
}
