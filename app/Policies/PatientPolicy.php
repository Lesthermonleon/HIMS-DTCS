<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * Policy for governing access to Patient Information module.
 * Only System Administrator and Doctor / Physician can perform patient actions.
 */
class PatientPolicy
{
    /**
     * Determine whether the user can view any patient records.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessPatients();
    }

    /**
     * Determine whether the user can view a specific patient profile.
     */
    public function view(User $user, Patient $patient): bool
    {
        if (! $user->canAccessPatients()) {
            return false;
        }

        if ($user->hasRole('doctor') && ! $user->isAdmin()) {
            return $patient->isAuthorizedForDoctor($user);
        }

        return true;
    }



    /**
     * Determine whether the user can delete (archive) a patient record.
     */
    public function delete(User $user, Patient $patient): bool
    {
        return $user->canAccessPatients();
    }
}
