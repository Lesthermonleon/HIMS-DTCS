<?php

namespace App\Policies;

use App\Models\RadiologyRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RadiologyRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any radiology requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'doctor', 'rad-tech', 'radiologist']);
    }

    /**
     * Determine whether the user can view the specific radiology request.
     */
    public function view(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if ($user->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return $radiologyRequest->doctor_id === $user->id;
        }

        return $user->hasAnyRole(['admin', 'super_admin', 'rad-tech', 'radiologist']);
    }

    /**
     * Determine whether the user can create radiology requests (Doctors only).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('doctor');
    }

    /**
     * Determine whether the user can update/edit the radiology request (Doctors when Pending).
     */
    public function update(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if ($radiologyRequest->status !== 'Pending') {
            return false;
        }

        return $user->hasRole('doctor');
    }

    /**
     * Determine whether the user can delete/cancel the radiology request (Doctors when Pending).
     */
    public function delete(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if ($radiologyRequest->status !== 'Pending') {
            return false;
        }

        return $user->hasRole('doctor');
    }

    /**
     * Determine whether the user can schedule/process the imaging procedure (Rad Techs when Pending).
     */
    public function schedule(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if ($radiologyRequest->status !== 'Pending') {
            return false;
        }

        return $user->hasRole('rad-tech');
    }

    /**
     * Determine whether the user can start the imaging procedure (Rad Techs when Scheduled).
     */
    public function start(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if ($radiologyRequest->status !== 'Scheduled') {
            return false;
        }

        return $user->hasRole('rad-tech');
    }

    /**
     * Determine whether the user can upload scan images/records (Rad Techs when Scheduled or In Progress).
     */
    public function uploadImage(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if (! in_array($radiologyRequest->status, ['Scheduled', 'In Progress'])) {
            return false;
        }

        return $user->hasRole('rad-tech');
    }

    /**
     * Determine whether the user can mark the imaging procedure as completed (Rad Techs when Scheduled or In Progress).
     */
    public function complete(User $user, RadiologyRequest $radiologyRequest): bool
    {
        if (! in_array($radiologyRequest->status, ['Scheduled', 'In Progress'])) {
            return false;
        }

        return $user->hasRole('rad-tech');
    }
}
