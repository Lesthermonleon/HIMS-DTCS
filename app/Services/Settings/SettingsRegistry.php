<?php

namespace App\Services\Settings;

class SettingsRegistry
{
    public const SCOPE_SUPER_ADMIN = 'super_admin';
    public const SCOPE_ADMIN       = 'admin';

    /**
     * Settings definitions catalogue.
     */
    public static function definitions(): array
    {
        return [
            // ── Super Admin Scope: Security & Governance ─────────────────────
            'security.password_min_length' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'integer',
                'default'     => 8,
                'rules'       => ['required', 'integer', 'min:8', 'max:64'],
                'label'       => 'Minimum Password Length',
                'description' => 'Minimum character length required for all user passwords (8 to 64).',
            ],
            'security.password_require_mixed_case' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'boolean',
                'default'     => false,
                'rules'       => ['required', 'boolean'],
                'label'       => 'Require Mixed Case',
                'description' => 'Enforce at least one uppercase and one lowercase letter.',
            ],
            'security.password_require_numbers' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'boolean',
                'default'     => false,
                'rules'       => ['required', 'boolean'],
                'label'       => 'Require Numbers',
                'description' => 'Enforce at least one numeric digit (0-9).',
            ],
            'security.password_require_symbols' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'boolean',
                'default'     => false,
                'rules'       => ['required', 'boolean'],
                'label'       => 'Require Special Symbols',
                'description' => 'Enforce at least one special character / symbol.',
            ],
            'security.session_inactivity_minutes' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'integer_nullable',
                'default'     => null,
                'rules'       => ['nullable', 'integer', 'min:5', 'max:1440'],
                'label'       => 'Server Session Inactivity Timeout (Minutes)',
                'description' => 'Server-side inactivity timeout in minutes (leave blank to rely on session lifetime). Separate from 3-min screen lock.',
            ],
            'security.otp_expires_minutes' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'integer_nullable',
                'default'     => null,
                'rules'       => ['nullable', 'integer', 'min:1', 'max:15'],
                'label'       => 'Email OTP Expiration (Minutes)',
                'description' => 'Runtime override for email verification OTP lifespan (1 to 15 minutes).',
            ],
            'maintenance.enabled' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'boolean',
                'default'     => false,
                'rules'       => ['required', 'boolean'],
                'label'       => 'Maintenance Mode',
                'description' => 'When active, only Super Administrators can access the system; others see a maintenance page.',
            ],
            'maintenance.message' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'string',
                'default'     => 'The hospital system is currently undergoing scheduled maintenance. Please check back shortly.',
                'rules'       => ['required', 'string', 'max:500'],
                'label'       => 'Maintenance Notice Message',
                'description' => 'Message displayed to staff and users during maintenance.',
            ],
            'maintenance.expected_back' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'string_nullable',
                'default'     => null,
                'rules'       => ['nullable', 'string', 'max:100'],
                'label'       => 'Expected Completion Time',
                'description' => 'Estimated time when maintenance will conclude.',
            ],
            'backup.enabled' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'boolean',
                'default'     => false,
                'rules'       => ['required', 'boolean'],
                'label'       => 'Automatic Daily Backup',
                'description' => 'Enable scheduled daily database backup job.',
            ],
            'backup.time' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'string',
                'default'     => '02:00',
                'rules'       => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
                'label'       => 'Scheduled Backup Time (HH:MM)',
                'description' => '24-hour UTC/server time for daily backup execution.',
            ],
            'backup.retention_days' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'integer',
                'default'     => 30,
                'rules'       => ['required', 'integer', 'min:1', 'max:365'],
                'label'       => 'Backup Retention (Days)',
                'description' => 'Number of days to preserve backup files before pruning (at least 3 latest backups are always retained).',
            ],
            'governance.admin_assignable_roles' => [
                'scope'       => self::SCOPE_SUPER_ADMIN,
                'type'        => 'array',
                'default'     => ['med-tech', 'rad-tech', 'radiologist', 'pharmacist', 'dietitian', 'or-coordinator'],
                'rules'       => ['required', 'array'],
                'label'       => 'Admin-Assignable Staff Roles',
                'description' => 'Role slugs that ordinary Administrators are permitted to create and assign.',
            ],

            // ── Admin Scope: Hospital Operations & Branding ─────────────────
            'hospital.name' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string',
                'default'     => 'Diagnostic, Treatment & Clinical Services',
                'rules'       => ['required', 'string', 'max:150'],
                'label'       => 'Hospital / Facility Name',
                'description' => 'Official name printed on reports, prescriptions, and official documents.',
            ],
            'hospital.tagline' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => 'Hospital Information Management System (HIMS)',
                'rules'       => ['nullable', 'string', 'max:200'],
                'label'       => 'Hospital Tagline / Subtitle',
                'description' => 'Secondary header or organizational description.',
            ],
            'hospital.address' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => 'General Santos City, Philippines',
                'rules'       => ['nullable', 'string', 'max:300'],
                'label'       => 'Physical Address',
                'description' => 'Facility location printed on formal outputs.',
            ],
            'hospital.phone' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => '+63 (083) 552-0000',
                'rules'       => ['nullable', 'string', 'max:50'],
                'label'       => 'Contact Telephone Number',
                'description' => 'Primary switchboard or emergency hotline number.',
            ],
            'hospital.email' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => 'contact@dtcs-hims.ph',
                'rules'       => ['nullable', 'email', 'max:100'],
                'label'       => 'Contact Email Address',
                'description' => 'Official facility email address.',
            ],
            'hospital.registration_no' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => 'DOH-LIC-2026-0042',
                'rules'       => ['nullable', 'string', 'max:100'],
                'label'       => 'Regulatory License / Accreditation No.',
                'description' => 'Department of Health license or accreditation reference.',
            ],
            'hospital.logo_path' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string_nullable',
                'default'     => null,
                'rules'       => ['nullable', 'string', 'max:255'],
                'label'       => 'Hospital Logo Storage Path',
                'description' => 'Relative path to official facility logo on protected storage.',
            ],
            'hours.weekly' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'array',
                'default'     => [
                    'monday'    => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'tuesday'   => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'wednesday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'thursday'  => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'friday'    => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'saturday'  => ['open' => '08:00', 'close' => '12:00', 'closed' => false],
                    'sunday'    => ['open' => null,    'close' => null,    'closed' => true],
                ],
                'rules'       => ['required', 'array'],
                'label'       => 'Standard Weekly Operating Hours',
                'description' => 'Regular business and outpatient consultation hours.',
            ],
            'hours.exceptions' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'array',
                'default'     => [],
                'rules'       => ['nullable', 'array'],
                'label'       => 'Holiday & Special Schedule Exceptions',
                'description' => 'Calendar date exceptions with alternate hours or full closure.',
            ],
            'queue.prefix_diagnostic' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string',
                'default'     => 'DIAG-',
                'rules'       => ['required', 'string', 'regex:/^[A-Z]{2,6}-$/', 'different:queue.prefix_treatment'],
                'label'       => 'Diagnostic Queue Prefix',
                'description' => 'Prefix for laboratory and radiology tickets (e.g. DIAG-). Must end with a dash.',
            ],
            'queue.prefix_treatment' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'string',
                'default'     => 'TX-',
                'rules'       => ['required', 'string', 'regex:/^[A-Z]{2,6}-$/', 'different:queue.prefix_diagnostic'],
                'label'       => 'Treatment Queue Prefix',
                'description' => 'Prefix for pharmacy and surgical tickets (e.g. TX-). Must end with a dash.',
            ],
            'ops.queue_delay_threshold_minutes' => [
                'scope'       => self::SCOPE_ADMIN,
                'type'        => 'integer',
                'default'     => 240,
                'rules'       => ['required', 'integer', 'min:15', 'max:1440'],
                'label'       => 'Queue Delay Warning Threshold (Minutes)',
                'description' => 'Requests waiting longer than this threshold are flagged in the Operations Monitor.',
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function getScope(string $key): ?string
    {
        return self::definitions()[$key]['scope'] ?? null;
    }

    public static function getDefault(string $key): mixed
    {
        return self::definitions()[$key]['default'] ?? null;
    }

    public static function getRulesForScope(string $scope): array
    {
        $rules = [];
        foreach (self::definitions() as $key => $def) {
            if ($def['scope'] === $scope) {
                $rules[$key] = $def['rules'];
            }
        }
        return $rules;
    }
}
