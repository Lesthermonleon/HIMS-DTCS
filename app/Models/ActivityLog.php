<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity log for auditing user actions across all modules.
 *
 * This is an APPEND-ONLY table. Records must never be modified or deleted.
 * Corrections are made by creating a new audit event.
 *
 * Severity levels:
 *   INFO     — Routine successful actions (login, record created, etc.)
 *   WARNING  — Elevated events requiring awareness (failed login, session replaced, etc.)
 *   CRITICAL — Security-significant events (account locked, unauthorized access, etc.)
 *
 * Result values:
 *   SUCCESS — Action completed successfully
 *   FAILED  — Action attempted but failed (wrong credentials, validation error)
 *   BLOCKED — Action was blocked/prevented (account lock, session mismatch, etc.)
 */
class ActivityLog extends Model
{
    // ──────────────────────────── Severity Constants ────────────────────────────
    public const SEVERITY_INFO     = 'INFO';
    public const SEVERITY_WARNING  = 'WARNING';
    public const SEVERITY_CRITICAL = 'CRITICAL';

    // ──────────────────────────── Result Constants ──────────────────────────────
    public const RESULT_SUCCESS = 'SUCCESS';
    public const RESULT_FAILED  = 'FAILED';
    public const RESULT_BLOCKED = 'BLOCKED';

    // ──────────────────────── Module Category Constants ─────────────────────────
    /** Authentication module events (login, logout, session, lockout) */
    public const MODULE_AUTH              = 'Authentication';
    public const MODULE_SECURITY          = 'Security';
    public const MODULE_SYSTEM_SETTINGS   = 'System Settings';
    public const MODULE_MASTER_CATALOG    = 'Master Catalog';
    public const MODULE_CLINICAL_CODES    = 'Clinical Codes';
    public const MODULE_INTEGRATIONS      = 'Integrations';
    public const MODULE_BACKUP            = 'Backup & Maintenance';
    public const MODULE_AUDIT_VAULT       = 'Audit Vault';
    public const MODULE_HOSPITAL_SETTINGS = 'Hospital Settings';

    /** Clinical modules — counted as Clinical Activity */
    public const CLINICAL_MODULES = ['Patient', 'Laboratory', 'Radiology', 'Pharmacy', 'Surgery', 'Diet'];

    /** Administrative modules — counted as System & Admin Audit */
    public const ADMIN_MODULES = [
        'User Management', 'System Audit Logs', 'System Administration', 'System',
        'Security', 'System Settings', 'Master Catalog', 'Clinical Codes',
        'Integrations', 'Backup & Maintenance', 'Audit Vault', 'Hospital Settings'
    ];

    /** Super-admin-only privileged governance modules */
    public const SUPER_ADMIN_MODULES = [
        'Security', 'System Settings', 'Master Catalog', 'Clinical Codes',
        'Backup & Maintenance', 'Audit Vault'
    ];

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'loggable_type',
        'loggable_id',
        'ip_address',
        'severity',
        'result',
        'logged_at',
    ];

    protected $casts = ['logged_at' => 'datetime'];

    /**
     * Application-level immutability enforcement:
     * ActivityLog records are strictly append-only.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Activity logs are append-only. Modification is strictly prohibited.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Activity logs are append-only. Deletion is strictly prohibited.');
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Polymorphic relation to the entity being logged.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────── Accessors ─────────────────────────────────────

    /**
     * Bootstrap CSS badge class for the severity level.
     * Follows the Green/Red/Neutral design system:
     *   INFO     → green (success)
     *   WARNING  → red-subtle (danger-subtle) — avoids yellow which is not in the palette
     *   CRITICAL → red (danger)
     */
    public function getSeverityBadgeClassAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'bg-danger text-white border border-danger',
            self::SEVERITY_WARNING  => 'bg-danger-subtle text-danger border border-danger-subtle',
            default                 => 'bg-success-subtle text-success border border-success-subtle',
        };
    }

    /**
     * Bootstrap CSS badge class for the result value.
     */
    public function getResultBadgeClassAttribute(): string
    {
        return match ($this->result) {
            self::RESULT_FAILED  => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::RESULT_BLOCKED => 'bg-danger text-white border border-danger',
            self::RESULT_SUCCESS => 'bg-success-subtle text-success border border-success-subtle',
            default              => 'bg-secondary-subtle text-secondary',
        };
    }

    /**
     * Bootstrap Icon class for the severity level.
     */
    public function getSeverityIconAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'bi-exclamation-octagon-fill',
            self::SEVERITY_WARNING  => 'bi-exclamation-triangle-fill',
            default                 => 'bi-info-circle-fill',
        };
    }
}
