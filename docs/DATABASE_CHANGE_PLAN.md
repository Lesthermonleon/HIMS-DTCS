# DTCS HIMS — Database Schema Change & Data Preservation Plan

**Document Version:** 2.0.0  
**Date:** October 18, 2026  
**Active Environment Database:** `newdb` (Local Dev / Staging per `.env`)  
**Automated Testing Database:** `ditc_hms` (Testing Database per `phpunit.xml`)  
**Schema Source of Truth:** `database/schema/ditc_hms_schema.sql` + `database/schema/panelist_redesign_additive.sql`  
**Mandate:** 100% Non-Destructive Data Preservation  
**Status:** DRAFT / PENDING ARCHITECTURAL REVIEW (Implementation Paused)

---

## 1. Non-Negotiable Database Safety Constraints

To guarantee zero data loss, zero table corruption, and clinical continuity, the following rules are strictly enforced:

> [!CAUTION]
> **PROHIBITED COMMANDS:**
> - NEVER run `php artisan migrate:fresh`
> - NEVER run `php artisan db:wipe`
> - NEVER execute raw `DROP TABLE`, `TRUNCATE`, or `ALTER TABLE ... DROP COLUMN` on production/development tables.
> - NEVER overwrite or wipe existing user records, patient demographics, clinical requests, or audit log history.

All database evolution must be **strictly additive**, backward-compatible, and safely reversible.

---

## 2. Current Baseline & Database State

### 2.1 Active Databases
1. **Development & Staging (`newdb`):**
   - Active database configured in `.env`.
   - Contains 56 active tables.
   - **Current Record Counts:**
     - `patients`: 30 real demographic records (master records preserved).
     - `users`: 9 active system users (Super Admin `superadmin@dtcs.com`, Admin `kirito.zoldyck75@gmail.com`, doctors, clinical staff).
     - `roles`: 10 roles (`admin`, `doctor`, `med-tech`, `rad-tech`, `radiologist`, `pharmacist`, `dietitian`, `or-coordinator`, `patient`, `super_admin`).
     - `lab_results`: 6 diagnostic records.
     - `prescriptions`: 7 medication records.
     - `appointments`: 0 records (table created additively).
     - `invoices`: 0 records (table created additively).
2. **Automated Testing (`ditc_hms`):**
   - Configured in `phpunit.xml` with `DatabaseTransactions` trait.
   - Additive schema patches applied to mirror staging.

---

## 3. Detailed Additive Schema Modifications

All changes are consolidated in `database/schema/panelist_redesign_additive.sql`:

### Patch 1: User-Patient Bidirectional Association
- **Rationale:** Connect portal user accounts to patient clinical files without breaking upstream external patient master compatibility.
- **SQL Specification:**
  ```sql
  ALTER TABLE `users` 
  ADD COLUMN `patient_id` BIGINT UNSIGNED NULL AFTER `department`;

  ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_patient_id` 
  FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) 
  ON DELETE SET NULL ON UPDATE CASCADE;

  ALTER TABLE `patients`
  ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `patient_no`;

  ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_user_id` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE SET NULL ON UPDATE CASCADE;
  ```

### Patch 2: Register Patient Role
- **Rationale:** Enable the `patient` user type with dedicated routing to `patient.dashboard`.
- **SQL Specification:**
  ```sql
  INSERT INTO `roles` (`name`, `slug`, `dashboard_route`, `created_at`, `updated_at`)
  SELECT 'Patient', 'patient', 'patient.dashboard', NOW(), NOW()
  WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `slug` = 'patient');
  ```

### Patch 3: Outpatient Appointments Table (`appointments`)
- **Rationale:** Enable structured consultation bookings for Doctor queues, Admin management, and Patient Portal self-service.
- **SQL Specification:**
  ```sql
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
  ```

### Patch 4: Invoices & Receipts Table (`invoices`)
- **Rationale:** Back the read-only Patient Portal "Bills & Receipts" view and enable future billing integration.
- **SQL Specification:**
  ```sql
  CREATE TABLE IF NOT EXISTS `invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_no` VARCHAR(64) NOT NULL UNIQUE,
    `patient_id` BIGINT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(64) NULL,
    `status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Void') NOT NULL DEFAULT 'Unpaid',
    `paid_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_invoices_patient` (`patient_id`),
    INDEX `idx_invoices_status` (`status`),
    CONSTRAINT `fk_invoices_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```

### Patch 5: Granular Diagnostic & Clinical Permissions
- **Rationale:** Provide discrete permissions for result entry, validation, and release.
- **SQL Specification:**
  ```sql
  INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`) VALUES
  ('Validate Laboratory Results', 'lis.results.verify', 'Diagnostics', NOW(), NOW());
  ```

### Patch 6: Idempotent Role-Permission Bindings
- **Rationale:** Bind granular permissions to department roles in `permission_role`.
- **SQL Specification:**
  ```sql
  -- Bind LIS permissions to med-tech
  INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
  SELECT p.id, r.id FROM `permissions` p, `roles` r
  WHERE r.slug = 'med-tech' AND p.slug IN ('lis.results.enter', 'lis.results.verify', 'lis.results.release');

  -- Bind PMS permissions to pharmacist
  INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
  SELECT p.id, r.id FROM `permissions` p, `roles` r
  WHERE r.slug = 'pharmacist' AND p.slug IN ('pms.prescriptions.verify', 'pms.dispense');
  ```

---

## 4. Impact on External Patient Master Integration

The executed schema updates do not alter the structure of patient demographic data:
- `patients` table retains all original fields (`patient_no`, `first_name`, `last_name`, `date_of_birth`, `gender`, `phone`, `email`, `address`, `status`).
- Only a nullable `user_id` foreign key was added to enable the adapter linking pattern.
- No second patient master table was created.
- Upstream patient synchronization remains 100% compatible.

---

## 5. Rollback Procedures

Should rollback ever be required, safe drop scripts are documented below (to be executed only with written authorization):

```sql
-- Reversible Rollback Script (DO NOT EXECUTE WITHOUT AUTHORIZATION)
-- 1. Unbind permissions
DELETE FROM `permission_role` WHERE `permission_id` IN (SELECT id FROM `permissions` WHERE slug IN ('lis.results.verify'));

-- 2. Drop additive tables (only if empty or backed up)
-- DROP TABLE IF EXISTS `appointments`;
-- DROP TABLE IF EXISTS `invoices`;

-- 3. Drop additive columns
-- ALTER TABLE `users` DROP FOREIGN KEY `fk_users_patient_id`, DROP COLUMN `patient_id`;
-- ALTER TABLE `patients` DROP FOREIGN KEY `fk_patients_user_id`, DROP COLUMN `user_id`;
```
