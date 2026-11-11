<?php

namespace App\Policies;

use App\Models\RadiologyReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RadiologyReportPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any radiology reports.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'radiologist', 'doctor', 'rad-tech']);
    }

    /**
     * Determine whether the user can view a specific radiology report.
     */
    public function view(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($user->hasRole('doctor') && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            // Doctors can only view Approved or Released reports for their own patients
            return in_array($radiologyReport->status, ['Approved', 'Released'], true)
                && $radiologyReport->radiologyRequest?->doctor_id === $user->id;
        }

        return $user->hasAnyRole(['admin', 'super_admin', 'radiologist', 'rad-tech']);
    }

    /**
     * Determine whether the user can create radiology reports.
     * Radiologists create reports; Radiology Staff can prepare preliminary draft reports when authorized.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('radiologist')) {
            return true;
        }

        if ($user->hasRole('rad-tech') && $user->hasPermission('ris.reports.prepare')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can edit/update a radiology report.
     * Allowed only while in Draft status. Approved/Released reports cannot be modified.
     */
    public function update(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($radiologyReport->status !== 'Draft') {
            return false;
        }

        if ($user->hasRole('radiologist')) {
            return true;
        }

        if ($user->hasRole('rad-tech') && $user->hasPermission('ris.reports.prepare')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete a draft report (Radiologists when Draft).
     */
    public function delete(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($radiologyReport->status !== 'Draft') {
            return false;
        }

        return $user->hasRole('radiologist');
    }

    /**
     * Determine whether the user can approve/finalize a report (Radiologists only, when Draft).
     */
    public function approve(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($radiologyReport->status !== 'Draft') {
            return false;
        }

        return $user->hasRole('radiologist');
    }

    /**
     * Determine whether the user can release a report to referring doctor (Radiologists when Approved).
     */
    public function release(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($radiologyReport->status !== 'Approved') {
            return false;
        }

        return $user->hasRole('radiologist');
    }

    /**
     * Determine whether the user can return a draft report for correction (Radiologists when Draft).
     */
    public function returnForCorrection(User $user, RadiologyReport $radiologyReport): bool
    {
        if ($radiologyReport->status !== 'Draft') {
            return false;
        }

        return $user->hasRole('radiologist');
    }
}
