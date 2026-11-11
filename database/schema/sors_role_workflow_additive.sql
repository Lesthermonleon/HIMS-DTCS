-- ============================================================================
-- DTCS HIMS — SORS Role-Based Workflow Additive Schema & RBAC Patch
-- Target Databases: newdb / dtcs_hms (Dev) & ditc_hms (Test)
-- Safety: Non-destructive, idempotent, MySQL 8.x compatible.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. ADDITIVE COLUMNS ON `surgery_schedules` (MySQL 8.x Idempotent Checks)
-- ----------------------------------------------------------------------------
ALTER TABLE `surgery_schedules`
    MODIFY COLUMN `surgical_team_id` BIGINT UNSIGNED NULL DEFAULT NULL;

SET @db_name = DATABASE();

SET @sql_actual_start = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'actual_start'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `actual_start` TIMESTAMP NULL DEFAULT NULL AFTER `status`'
);
PREPARE stmt FROM @sql_actual_start; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_actual_end = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'actual_end'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `actual_end` TIMESTAMP NULL DEFAULT NULL AFTER `actual_start`'
);
PREPARE stmt FROM @sql_actual_end; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_prev_readiness = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'previous_readiness_status'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `previous_readiness_status` VARCHAR(32) NULL DEFAULT NULL AFTER `actual_end`'
);
PREPARE stmt FROM @sql_prev_readiness; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_readiness_status = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'readiness_status'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `readiness_status` ENUM(\'Pending Prep\',\'Preparing\',\'Ready\') NOT NULL DEFAULT \'Pending Prep\' AFTER `previous_readiness_status`'
);
PREPARE stmt FROM @sql_readiness_status; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_readiness_notes = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'readiness_notes'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `readiness_notes` TEXT NULL AFTER `readiness_status`'
);
PREPARE stmt FROM @sql_readiness_notes; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_readiness_updated_by = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'readiness_updated_by'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `readiness_updated_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `readiness_notes`'
);
PREPARE stmt FROM @sql_readiness_updated_by; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_readiness_updated_at = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'surgery_schedules' AND COLUMN_NAME = 'readiness_updated_at'),
    'SELECT 1',
    'ALTER TABLE `surgery_schedules` ADD COLUMN `readiness_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `readiness_updated_by`'
);
PREPARE stmt FROM @sql_readiness_updated_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 2. IDEMPOTENT ROLE SEEDING (`or-staff` AND REUSE OF `or-coordinator`)
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `dashboard_route`, `description`, `created_at`, `updated_at`)
VALUES
    ('OR Coordinator', 'or-coordinator', 'surgery.dashboard', 'Manage surgery requests, schedule operations, assign operating rooms, and coordinate OR readiness', NOW(), NOW()),
    ('OR Staff',       'or-staff',       'surgery.dashboard', 'View assigned surgery schedules, update operating-room preparation/readiness, and record authorized operational updates', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name`            = VALUES(`name`),
    `dashboard_route` = VALUES(`dashboard_route`),
    `description`     = VALUES(`description`),
    `updated_at`      = NOW();

-- ----------------------------------------------------------------------------
-- 3. IDEMPOTENT SORS PERMISSIONS SEEDING
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`)
VALUES
    ('View Surgery Requests',      'sors.requests.view',      'SORS', NOW(), NOW()),
    ('Create Surgery Requests',    'sors.requests.create',    'SORS', NOW(), NOW()),
    ('Manage Surgery Requests',    'sors.requests.manage',    'SORS', NOW(), NOW()),
    ('View OR Schedules',          'sors.schedules.view',     'SORS', NOW(), NOW()),
    ('Manage OR Schedules',        'sors.schedules.manage',   'SORS', NOW(), NOW()),
    ('Update OR Readiness',        'sors.readiness.update',   'SORS', NOW(), NOW()),
    ('Execute OR Procedures',      'sors.procedures.execute', 'SORS', NOW(), NOW()),
    ('View Surgery Reports',       'sors.reports.view',       'SORS', NOW(), NOW());

-- ----------------------------------------------------------------------------
-- 4. IDEMPOTENT ROLE-PERMISSION BINDINGS FOR SORS (LEAST PRIVILEGE)
-- ----------------------------------------------------------------------------

-- Doctor (`doctor`): create & view own surgery requests, view own schedules/calendar, view own surgery reports
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
CROSS JOIN `roles` r
WHERE r.slug = 'doctor'
  AND p.slug IN (
      'sors.requests.view',
      'sors.requests.create',
      'sors.schedules.view',
      'sors.reports.view'
  );

-- OR Coordinator (`or-coordinator`): view & manage requests, view & manage schedules, update readiness, execute procedures, view operational reports
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
CROSS JOIN `roles` r
WHERE r.slug = 'or-coordinator'
  AND p.slug IN (
      'sors.requests.view',
      'sors.requests.manage',
      'sors.schedules.view',
      'sors.schedules.manage',
      'sors.readiness.update',
      'sors.procedures.execute',
      'sors.reports.view'
  );

-- OR Staff (`or-staff`): view assigned schedules/calendar, update OR preparation/readiness on assigned cases
-- Note: `sors.procedures.execute` (recording actual surgery start/completion) is reserved for `or-coordinator`
-- by default until hospital-client confirmation, though supported if explicitly granted to a role.
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
CROSS JOIN `roles` r
WHERE r.slug = 'or-staff'
  AND p.slug IN (
      'sors.schedules.view',
      'sors.readiness.update'
  );

-- System Administrator (`admin`): operational monitoring read access only (no automatic clinical or scheduling authority)
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
CROSS JOIN `roles` r
WHERE r.slug = 'admin'
  AND p.slug IN (
      'sors.requests.view',
      'sors.schedules.view',
      'sors.reports.view'
  );
