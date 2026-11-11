-- ============================================================================
-- DTCS HIMS — Super Admin / Admin Architecture: IDEMPOTENT SEED DATA
-- ----------------------------------------------------------------------------
-- SAFE TO RE-RUN. Uses INSERT ... ON DUPLICATE KEY UPDATE / INSERT IGNORE only.
-- Does NOT create any user account and does NOT set any password.
-- (Create the first Super Admin with:  php artisan hims:super-admin:provision)
--
-- Prerequisite: database/schema/ditc_hms_schema.sql has been applied.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. ROLE: super_admin  (existing role ids / slugs are untouched)
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `dashboard_route`, `description`, `created_at`, `updated_at`) VALUES
('Super Administrator', 'super_admin', 'super-admin.dashboard',
 'Master system authority: governance, security, catalogs. No automatic clinical or patient-data access.',
 NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name`            = VALUES(`name`),
    `dashboard_route` = VALUES(`dashboard_route`),
    `description`     = VALUES(`description`),
    `updated_at`      = NOW();

-- ----------------------------------------------------------------------------
-- 2. PERMISSION CATALOGUE for the new capabilities
-- ----------------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`) VALUES
('View Super Admin dashboard',               'superadmin.dashboard.view',            'Super Admin', NOW(), NOW()),
('Manage security settings',                 'settings.security.manage',             'Super Admin', NOW(), NOW()),
('Manage maintenance mode',                  'settings.maintenance.manage',          'Super Admin', NOW(), NOW()),
('Manage database backups',                  'settings.backup.manage',               'Super Admin', NOW(), NOW()),
('Manage master diagnostic/service catalog', 'catalog.master.manage',                'Super Admin', NOW(), NOW()),
('Manage clinical code systems',             'clinical-codes.manage',                'Super Admin', NOW(), NOW()),
('View protected audit vault',               'audit.vault.view',                     'Super Admin', NOW(), NOW()),
('Export protected audit vault',             'audit.vault.export',                   'Super Admin', NOW(), NOW()),
('Manage privileged Admin accounts',         'accounts.admin.manage',                'Super Admin', NOW(), NOW()),
('Authorize Doctor account provisioning',    'accounts.doctor-provisioning.authorize','Super Admin', NOW(), NOW()),
('Review roles and access structure',        'access.roles.review',                  'Super Admin', NOW(), NOW()),
('Manage Doctor accounts',                   'accounts.doctor.manage',               'Admin',       NOW(), NOW()),
('Manage Staff accounts',                    'accounts.staff.manage',                'Admin',       NOW(), NOW()),
('Manage hospital branding and settings',    'settings.hospital.manage',             'Admin',       NOW(), NOW()),
('View operational monitoring',              'operations.monitor.view',              'Admin',       NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name`       = VALUES(`name`),
    `module`     = VALUES(`module`),
    `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- 3. ROLE -> PERMISSION GRANTS (explicit lists; nothing is "grant all")
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`slug` = 'super_admin'
WHERE p.`slug` IN (
    'superadmin.dashboard.view', 'settings.security.manage', 'settings.maintenance.manage',
    'settings.backup.manage', 'catalog.master.manage', 'clinical-codes.manage',
    'audit.vault.view', 'audit.vault.export',
    'accounts.admin.manage', 'accounts.doctor-provisioning.authorize', 'access.roles.review'
);

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`slug` = 'admin'
WHERE p.`slug` IN (
    'accounts.doctor.manage', 'accounts.staff.manage',
    'settings.hospital.manage', 'operations.monitor.view'
);

-- ----------------------------------------------------------------------------
-- 4. CLINICAL CODE SYSTEM REGISTRY (identifiers only — NO code rows are seeded)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `clinical_code_systems` (`system_key`, `name`, `kind`, `license_status`, `created_at`, `updated_at`) VALUES
('ICD-11', 'ICD-11 (WHO) — diagnosis classification',      'diagnosis',   'unverified', NOW(), NOW()),
('CPT',    'CPT (AMA) — procedure codes',                   'procedure',   'unverified', NOW(), NOW()),
('LOINC',  'LOINC (Regenstrief) — laboratory observations', 'observation', 'unverified', NOW(), NOW());
