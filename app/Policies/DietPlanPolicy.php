<?php

namespace App\Policies;

use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\User;

/**
 * Policy governing DietPlan access in the Doctor-Managed DNMS workflow.
 *
 * Doctors create, revise, and complete diet plans for their own authorized patients' diet requests.
 * Admin / Super Admin are read-only observers with no clinical mutation authority.
 */
class DietPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'admin', 'super_admin']);
    }

    public function view(User $user, DietPlan $dietPlan): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->hasRole('doctor') && $this->isOwnedByDoctor($user, $dietPlan);
    }

    /**
     * Only doctors may create diet plans, and when a specific DietRequest is supplied,
     * the doctor must own the request and be authorized for the patient.
     */
    public function create(User $user, ?DietRequest $dietRequest = null): bool
    {
        if (! $user->hasRole('doctor')) {
            return false;
        }

        if ($dietRequest !== null) {
            return (int) $dietRequest->doctor_id === (int) $user->id
                && $dietRequest->patient !== null
                && $dietRequest->patient->isAuthorizedForDoctor($user);
        }

        return true;
    }

    public function update(User $user, DietPlan $dietPlan): bool
    {
        return $user->hasRole('doctor')
            && $this->isOwnedByDoctor($user, $dietPlan)
            && $dietPlan->status !== 'Completed';
    }

    public function complete(User $user, DietPlan $dietPlan): bool
    {
        return $user->hasRole('doctor')
            && $this->isOwnedByDoctor($user, $dietPlan)
            && $dietPlan->status !== 'Completed';
    }

    public function delete(User $user, DietPlan $dietPlan): bool
    {
        return $user->hasRole('doctor')
            && $this->isOwnedByDoctor($user, $dietPlan)
            && $dietPlan->status !== 'Completed';
    }

    private function isOwnedByDoctor(User $user, DietPlan $dietPlan): bool
    {
        $request = $dietPlan->dietRequest;
        if ($request === null || (int) $request->doctor_id !== (int) $user->id) {
            return false;
        }

        return $request->patient === null || $request->patient->isAuthorizedForDoctor($user);
    }
}
