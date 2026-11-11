<?php

namespace App\Traits;

/**
 * HasStatusBadge — provides a Bootstrap 5 badge color for a model's `status` field.
 * Use: add `use HasStatusBadge;` to any model with a `status` column.
 */
trait HasStatusBadge
{
    /**
     * Returns a Bootstrap 5 background class for the current status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status ?? '') {
            'Pending'              => 'secondary',
            'In Progress',
            'Scheduled',
            'Revised'              => 'info text-dark',
            'Completed', 'Released',
            'Dispensed', 'Active'  => 'success',
            'Verified'             => 'primary',
            'Encoded'              => 'warning text-dark',
            'Validated'            => 'info',
            'Approved',
            'Draft'                => 'warning text-dark',
            'Cancelled', 'Refused',
            'Rejected', 'Voided'   => 'danger',
            'Returned', 'Refunded',
            'Partially Dispensed'  => 'warning text-dark',
            default                => 'secondary',
        };
    }
}
