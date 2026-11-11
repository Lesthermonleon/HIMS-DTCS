<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PharmacyReplenishmentRequest — STATIC / DEMONSTRATION ONLY record.
 * Never transmits to an external system, never creates a live Purchase Order,
 * and never mutates inventory stock quantities.
 */
class PharmacyReplenishmentRequest extends Model
{
    protected $table = 'pharmacy_replenishment_requests';

    public const STATUS_DRAFT             = 'Draft';
    public const STATUS_PENDING_DEMO      = 'Pending (Demo)';
    public const STATUS_SUBMITTED_DEMO    = 'Submitted (Demo)';
    public const STATUS_ACKNOWLEDGED_DEMO = 'Acknowledged (Demo)';
    public const STATUS_REJECTED_DEMO     = 'Rejected (Demo)';

    public const DEMO_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_DEMO,
        self::STATUS_SUBMITTED_DEMO,
        self::STATUS_ACKNOWLEDGED_DEMO,
        self::STATUS_REJECTED_DEMO,
    ];

    protected $fillable = [
        'request_no',
        'pharmacist_id',
        'requesting_department',
        'target_subsystem',
        'items_payload',
        'total_items',
        'total_units_requested',
        'remarks',
        'demo_status',
        'is_demo',
        'submitted_at',
    ];

    protected $casts = [
        'items_payload'         => 'array',
        'total_items'           => 'integer',
        'total_units_requested' => 'integer',
        'is_demo'               => 'boolean',
        'submitted_at'          => 'datetime',
    ];

    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }

    public function getStatusAttribute(): string
    {
        return $this->demo_status ?? self::STATUS_DRAFT;
    }

    public function setStatusAttribute(string $value): void
    {
        $this->attributes['demo_status'] = $value;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->remarks;
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['remarks'] = $value;
    }

    public function getPriorityAttribute(): string
    {
        return 'High';
    }

    public function getDemoBadgeClassAttribute(): string
    {
        return match ($this->demo_status) {
            'Draft'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary-subtle',
            'Pending (Demo)'      => 'bg-warning bg-opacity-10 text-warning-emphasis border-warning-subtle',
            'Submitted (Demo)'    => 'bg-primary bg-opacity-10 text-primary border-primary-subtle',
            'Acknowledged (Demo)' => 'bg-success bg-opacity-10 text-success border-success-subtle',
            'Rejected (Demo)'     => 'bg-danger bg-opacity-10 text-danger border-danger-subtle',
            default               => 'bg-secondary bg-opacity-10 text-secondary',
        };
    }
}
