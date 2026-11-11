-- ============================================================================
-- DTCS HIMS — Panelist Redesign Additive Schema Patch
-- Target Databases: dtcs_hms (Dev) / ditc_hms (Test)
-- Safety: Non-destructive, idempotent, preserves all existing tables and data.
-- ============================================================================

-- 1. Ensure Patient Role Exists
INSERT INTO `roles` (`name`, `slug`, `dashboard_route`, `created_at`, `updated_at`)
SELECT 'Patient', 'patient', 'patient.dashboard', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `slug` = 'patient');

-- 2. Create Outpatient Appointments & Scheduling Engine Table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_no` VARCHAR(64) NOT NULL UNIQUE,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `doctor_id` BIGINT UNSIGNED NOT NULL,
  `department` VARCHAR(128) NULL,
  `scheduled_at` DATETIME NOT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
  `type` VARCHAR(64) NOT NULL DEFAULT 'In-Person Consultation',
  `reason` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('Requested', 'Confirmed', 'CheckedIn', 'In Consultation', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Requested',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_appointments_patient` (`patient_id`),
  INDEX `idx_appointments_doctor` (`doctor_id`),
  INDEX `idx_appointments_schedule` (`scheduled_at`, `status`),
  CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Operational Invoicing & Billing Ledger Table
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` VARCHAR(64) NOT NULL UNIQUE,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `description` VARCHAR(255) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Voided') NOT NULL DEFAULT 'Unpaid',
  `payment_method` VARCHAR(64) NULL,
  `payment_reference` VARCHAR(128) NULL,
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_invoices_patient` (`patient_id`),
  INDEX `idx_invoices_status` (`status`),
  CONSTRAINT `fk_invoices_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed Granular Permissions for Clinical, Diagnostic, Pharmacy, and Patient Roles
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`) VALUES
('Access Medical Records', 'clinical.records.view', 'Clinical Care', NOW(), NOW()),
('Create Clinical Consultation', 'clinical.consultations.create', 'Clinical Care', NOW(), NOW()),
('Sign Clinical Notes', 'clinical.notes.sign', 'Clinical Care', NOW(), NOW()),
('Order Diagnostics', 'diagnostics.order', 'Clinical Care', NOW(), NOW()),
('Prescribe Medication', 'clinical.prescribe', 'Clinical Care', NOW(), NOW()),
('Access MediSense AI', 'medisense.access', 'Clinical Care', NOW(), NOW()),
('Enter Lab Results', 'lis.results.enter', 'Laboratory', NOW(), NOW()),
('Release Lab Results', 'lis.results.release', 'Laboratory', NOW(), NOW()),
('Upload Radiology Images', 'ris.images.upload', 'Radiology', NOW(), NOW()),
('Release Radiology Report', 'ris.reports.release', 'Radiology', NOW(), NOW()),
('Verify Prescription', 'pms.prescriptions.verify', 'Pharmacy', NOW(), NOW()),
('Dispense Medication', 'pms.dispense', 'Pharmacy', NOW(), NOW()),
('Manage Appointments', 'appointments.manage', 'Hospital Operations', NOW(), NOW()),
('Access Patient Portal', 'patient.portal.access', 'Patient Portal', NOW(), NOW());

-- 5. Separate LIS verification permission (entry / verification / release are distinct)
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`) VALUES
('Verify Lab Results', 'lis.results.verify', 'Laboratory', NOW(), NOW());

-- 6. Role bindings for the permissions enforced by route middleware (idempotent).
--    Bindings preserve the pre-existing behaviour: Medical Technologists enter, verify and release;
--    Pharmacists verify and dispense. Remove a binding to separate those duties per facility policy.
INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`slug` = 'med-tech'
WHERE p.`slug` IN ('lis.results.enter', 'lis.results.verify', 'lis.results.release');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`slug` = 'pharmacist'
WHERE p.`slug` IN ('pms.prescriptions.verify', 'pms.dispense');

-- 7. Diagnostic Services Granular Permissions and Role Bindings
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`) VALUES
('View Lab Requests', 'lis.requests.view', 'Laboratory', NOW(), NOW()),
('Create Lab Requests', 'lis.requests.create', 'Laboratory', NOW(), NOW()),
('Process Lab Specimens', 'lis.specimens.process', 'Laboratory', NOW(), NOW()),
('View Imaging Requests', 'ris.requests.view', 'Radiology', NOW(), NOW()),
('Create Imaging Requests', 'ris.requests.create', 'Radiology', NOW(), NOW()),
('Manage Imaging Procedures', 'ris.procedures.manage', 'Radiology', NOW(), NOW()),
('Prepare Radiology Reports', 'ris.reports.prepare', 'Radiology', NOW(), NOW()),
('Review Radiology Reports', 'ris.reports.review', 'Radiology', NOW(), NOW()),
('Approve Radiology Reports', 'ris.reports.approve', 'Radiology', NOW(), NOW()),
('View Diagnostic Results', 'diagnostics.results.view', 'Clinical Care', NOW(), NOW());

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id` FROM `permissions` p JOIN `roles` r ON r.`slug` = 'doctor'
WHERE p.`slug` IN ('diagnostics.order', 'diagnostics.results.view', 'lis.requests.view', 'lis.requests.create', 'ris.requests.view', 'ris.requests.create');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id` FROM `permissions` p JOIN `roles` r ON r.`slug` = 'med-tech'
WHERE p.`slug` IN ('lis.requests.view', 'lis.specimens.process', 'lis.results.enter', 'lis.results.verify', 'lis.results.release');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id` FROM `permissions` p JOIN `roles` r ON r.`slug` = 'rad-tech'
WHERE p.`slug` IN ('ris.requests.view', 'ris.procedures.manage', 'ris.images.upload', 'ris.reports.prepare');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id` FROM `permissions` p JOIN `roles` r ON r.`slug` = 'radiologist'
WHERE p.`slug` IN ('ris.requests.view', 'ris.reports.review', 'ris.reports.approve', 'ris.reports.release', 'diagnostics.results.view');

