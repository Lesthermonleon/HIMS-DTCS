-- ============================================================================
-- DTCS HIMS — DNMS Doctor-Managed Workflow Additive Schema & RBAC Patch
-- Target Databases: newdb / dtcs_hms (Dev) & ditc_hms (Test)
-- Safety: Non-destructive, idempotent, MySQL 8.x compatible.
-- ============================================================================

SET NAMES utf8mb4;
SET @db_name = DATABASE();

-- 1. ADDITIVE COLUMNS ON `diet_plans`
SET @s = IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'diet_plans' AND COLUMN_NAME = 'assessment_notes'),
    'SELECT 1', 'ALTER TABLE `diet_plans` ADD COLUMN `assessment_notes` TEXT NULL AFTER `plan_details`');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'diet_plans' AND COLUMN_NAME = 'updated_by'),
    'SELECT 1', 'ALTER TABLE `diet_plans` ADD COLUMN `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'diet_plans' AND COLUMN_NAME = 'completed_by'),
    'SELECT 1', 'ALTER TABLE `diet_plans` ADD COLUMN `completed_by` BIGINT UNSIGNED NULL DEFAULT NULL');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'diet_plans' AND COLUMN_NAME = 'completed_at'),
    'SELECT 1', 'ALTER TABLE `diet_plans` ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. ROLES — `doctor` is the sole clinical actor for DNMS.
-- Existing `dietitian` role & user rows are preserved for historical FK integrity,
-- while their DNMS permissions are revoked below. `dietary-staff` is not created.

-- 3. IDEMPOTENT DNMS PERMISSIONS
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`)
VALUES
    ('View Diet Requests',            'dnms.requests.view',      'DNMS', NOW(), NOW()),
    ('Create Diet Requests',          'dnms.requests.create',    'DNMS', NOW(), NOW()),
    ('Manage Own Diet Requests',      'dnms.requests.manage',    'DNMS', NOW(), NOW()),
    ('Create Nutrition Assessments',  'dnms.assessments.create', 'DNMS', NOW(), NOW()),
    ('Update Nutrition Assessments',  'dnms.assessments.update', 'DNMS', NOW(), NOW()),
    ('View Diet Plans',               'dnms.plans.view',         'DNMS', NOW(), NOW()),
    ('Create Diet Plans',             'dnms.plans.create',       'DNMS', NOW(), NOW()),
    ('Update Diet Plans',             'dnms.plans.update',       'DNMS', NOW(), NOW()),
    ('Complete Diet Plans',           'dnms.plans.complete',     'DNMS', NOW(), NOW()),
    ('View Nutrition Reports',        'dnms.reports.view',       'DNMS', NOW(), NOW());

-- 4. REVOKE DNMS PERMISSIONS FROM `dietitian` & `super_admin`
DELETE pr FROM `permission_role` pr
INNER JOIN `roles` r ON r.id = pr.role_id
INNER JOIN `permissions` p ON p.id = pr.permission_id
WHERE r.slug IN ('dietitian', 'super_admin')
  AND p.module = 'DNMS';

-- 5. ROLE BINDINGS (DOCTOR-MANAGED WORKFLOW + ADMIN READ-ONLY)
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p CROSS JOIN `roles` r
WHERE r.slug = 'doctor'
  AND p.slug IN (
      'dnms.requests.view',
      'dnms.requests.create',
      'dnms.requests.manage',
      'dnms.assessments.create',
      'dnms.assessments.update',
      'dnms.plans.view',
      'dnms.plans.create',
      'dnms.plans.update',
      'dnms.plans.complete',
      'dnms.reports.view'
  );

-- Admin: read-only monitoring. Super Admin receives no automatic clinical authority.
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p CROSS JOIN `roles` r
WHERE r.slug = 'admin'
  AND p.slug IN ('dnms.requests.view','dnms.plans.view','dnms.reports.view');
