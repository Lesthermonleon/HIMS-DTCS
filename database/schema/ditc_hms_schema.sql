-- ============================================================================
-- DTCS Hospital Management System — Complete Database Schema

-- MySQL 8.x Compatible · Laravel Synchronized
-- Generated: 2026-08-16
--
-- Import into MySQL Workbench via: File → Run SQL Script
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ════════════════════════════════════════════════════════════════════════════
-- AUTHENTICATION & FRAMEWORK TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(255)    NOT NULL,
    `employee_id`       VARCHAR(255)    NULL DEFAULT NULL,
    `department`        VARCHAR(255)    NULL DEFAULT NULL,
    `patient_id`        BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional link to patient profile for Patient Portal users',
    `phone`             VARCHAR(20)     NULL DEFAULT NULL,
    `avatar`            VARCHAR(255)    NULL DEFAULT NULL,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `email`             VARCHAR(255)    NOT NULL,
    `email_verified_at` TIMESTAMP       NULL DEFAULT NULL,
    `password`          VARCHAR(255)    NOT NULL,
    `remember_token`    VARCHAR(100)    NULL DEFAULT NULL,
    `login_token`       VARCHAR(512)    NULL DEFAULT NULL,
    `active_session_id` VARCHAR(255)    NULL DEFAULT NULL,
    `last_activity_at`  TIMESTAMP       NULL DEFAULT NULL,
    `failed_attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_at`         TIMESTAMP       NULL DEFAULT NULL,
    `lockout_until`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    UNIQUE KEY `users_employee_id_unique` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email`      VARCHAR(255) NOT NULL,
    `token`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(255)    NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`    VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`    TEXT            NULL,
    `payload`       LONGTEXT        NOT NULL,
    `last_activity` INT             NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
    `key`        VARCHAR(255) NOT NULL,
    `value`      MEDIUMTEXT   NOT NULL,
    `expiration` INT          NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key`        VARCHAR(255) NOT NULL,
    `owner`      VARCHAR(255) NOT NULL,
    `expiration` INT          NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue`        VARCHAR(255)    NOT NULL,
    `payload`      LONGTEXT        NOT NULL,
    `attempts`     TINYINT UNSIGNED NOT NULL,
    `reserved_at`  INT UNSIGNED    NULL DEFAULT NULL,
    `available_at` INT UNSIGNED    NOT NULL,
    `created_at`   INT UNSIGNED    NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
    `id`             VARCHAR(255) NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `total_jobs`     INT          NOT NULL,
    `pending_jobs`   INT          NOT NULL,
    `failed_jobs`    INT          NOT NULL,
    `failed_job_ids` LONGTEXT     NOT NULL,
    `options`        MEDIUMTEXT   NULL,
    `cancelled_at`   INT          NULL DEFAULT NULL,
    `created_at`     INT          NOT NULL,
    `finished_at`    INT          NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`       VARCHAR(255)    NOT NULL,
    `connection` TEXT            NOT NULL,
    `queue`      TEXT            NOT NULL,
    `payload`    LONGTEXT        NOT NULL,
    `exception`  LONGTEXT        NOT NULL,
    `failed_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- RBAC TABLES
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `roles` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(255)    NOT NULL,
    `slug`            VARCHAR(255)    NOT NULL,
    `dashboard_route` VARCHAR(100)    NULL DEFAULT NULL COMMENT 'Named route to redirect after login',
    `description`     VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)    NOT NULL,
    `slug`       VARCHAR(255)    NOT NULL,
    `module`     VARCHAR(255)    NOT NULL,
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    `updated_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_user` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `role_user_role_id_foreign` (`role_id`),
    CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permission_role` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id`       BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    KEY `permission_role_role_id_foreign` (`role_id`),
    CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `permission_role_role_id_foreign`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- PATIENT HUB
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `patients` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_no`              VARCHAR(255)    NOT NULL,
    `user_id`                 BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional link to portal user account',
    `first_name`              VARCHAR(255)    NOT NULL,
    `last_name`               VARCHAR(255)    NOT NULL,
    `middle_name`             VARCHAR(255)    NULL DEFAULT NULL,
    `date_of_birth`           DATE            NOT NULL,
    `gender`                  ENUM('Male','Female','Other') NOT NULL,
    `blood_type`              VARCHAR(5)      NULL DEFAULT NULL,
    `address`                 TEXT            NULL,
    `phone`                   VARCHAR(20)     NULL DEFAULT NULL,
    `email`                   VARCHAR(255)    NULL DEFAULT NULL,
    `emergency_contact_name`  VARCHAR(255)    NULL DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20)     NULL DEFAULT NULL,
    `patient_type`            ENUM('Inpatient','Outpatient') NOT NULL DEFAULT 'Outpatient',
    `ward`                    VARCHAR(255)    NULL DEFAULT NULL,
    `bed_number`              VARCHAR(255)    NULL DEFAULT NULL,
    `clinical_findings`        TEXT            NULL DEFAULT NULL,
    `created_at`              TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`              TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`              TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `patients_patient_no_unique` (`patient_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- LIS — LABORATORY INFORMATION SYSTEM
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lab_test_categories` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)    NOT NULL,
    `code`        VARCHAR(255)    NOT NULL,
    `description` VARCHAR(255)    NULL DEFAULT NULL,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lab_test_categories_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lab_tests` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lab_test_category_id`  BIGINT UNSIGNED NOT NULL,
    `name`                  VARCHAR(255)    NOT NULL,
    `code`                  VARCHAR(255)    NOT NULL,
    `normal_range`          VARCHAR(255)    NULL DEFAULT NULL,
    `unit`                  VARCHAR(255)    NULL DEFAULT NULL,
    `method`                VARCHAR(255)    NULL DEFAULT NULL,
    `price`                 DECIMAL(8,2)    NULL DEFAULT NULL,
    `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`            TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lab_tests_code_unique` (`code`),
    KEY `lab_tests_lab_test_category_id_foreign` (`lab_test_category_id`),
    CONSTRAINT `lab_tests_lab_test_category_id_foreign` FOREIGN KEY (`lab_test_category_id`) REFERENCES `lab_test_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lab_requests` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_no`     VARCHAR(255)    NOT NULL,
    `patient_id`     BIGINT UNSIGNED NOT NULL,
    `doctor_id`      BIGINT UNSIGNED NOT NULL,
    `priority`       ENUM('Routine','Urgent','STAT') NOT NULL DEFAULT 'Routine',
    `status`         ENUM('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    `clinical_notes` TEXT            NULL,
    `specimen_type`  VARCHAR(255)    NULL DEFAULT NULL,
    `requested_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `received_at`    TIMESTAMP       NULL DEFAULT NULL,
    `completed_at`   TIMESTAMP       NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lab_requests_request_no_unique` (`request_no`),
    KEY `lab_requests_patient_id_foreign` (`patient_id`),
    KEY `lab_requests_doctor_id_foreign` (`doctor_id`),
    KEY `lab_requests_status_index` (`status`),
    KEY `idx_lab_req_doctor_status` (`doctor_id`, `status`),
    CONSTRAINT `lab_requests_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `lab_requests_doctor_id_foreign`  FOREIGN KEY (`doctor_id`)  REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lab_request_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lab_request_id`  BIGINT UNSIGNED NOT NULL,
    `lab_test_id`     BIGINT UNSIGNED NOT NULL,
    `status`          ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `lab_request_items_lab_request_id_foreign` (`lab_request_id`),
    KEY `lab_request_items_lab_test_id_foreign` (`lab_test_id`),
    KEY `lab_request_items_status_index` (`status`),
    CONSTRAINT `lab_request_items_lab_request_id_foreign` FOREIGN KEY (`lab_request_id`) REFERENCES `lab_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `lab_request_items_lab_test_id_foreign`    FOREIGN KEY (`lab_test_id`)    REFERENCES `lab_tests` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lab_results` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lab_request_item_id` BIGINT UNSIGNED NOT NULL,
    `technologist_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `result_value`        VARCHAR(255)    NULL DEFAULT NULL,
    `remarks`             TEXT            NULL,
    `status`              ENUM('Pending','Encoded','Validated','Released') NOT NULL DEFAULT 'Pending',
    `validated_by`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `released_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `validated_at`        TIMESTAMP       NULL DEFAULT NULL,
    `released_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `lab_results_lab_request_item_id_foreign` (`lab_request_item_id`),
    KEY `lab_results_technologist_id_index` (`technologist_id`),
    KEY `lab_results_validated_by_index` (`validated_by`),
    KEY `lab_results_released_by_index` (`released_by`),
    CONSTRAINT `lab_results_lab_request_item_id_foreign` FOREIGN KEY (`lab_request_item_id`) REFERENCES `lab_request_items` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `lab_results_technologist_id_foreign`      FOREIGN KEY (`technologist_id`)     REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `lab_results_validated_by_foreign`         FOREIGN KEY (`validated_by`)         REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `lab_results_released_by_foreign`          FOREIGN KEY (`released_by`)          REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- RIS — RADIOLOGY INFORMATION SYSTEM
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `radiology_requests` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_no`             VARCHAR(255)    NOT NULL,
    `patient_id`             BIGINT UNSIGNED NOT NULL,
    `doctor_id`              BIGINT UNSIGNED NOT NULL,
    `modality`               VARCHAR(255)    NOT NULL,
    `body_part`              VARCHAR(255)    NOT NULL,
    `clinical_information`   TEXT            NULL,
    `priority`               ENUM('Routine','Urgent','STAT') NOT NULL DEFAULT 'Routine',
    `status`                 ENUM('Pending','Scheduled','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    `requested_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `scheduled_at`           TIMESTAMP       NULL DEFAULT NULL,
    `completed_at`           TIMESTAMP       NULL DEFAULT NULL,
    `created_at`             TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `radiology_requests_request_no_unique` (`request_no`),
    KEY `radiology_requests_patient_id_foreign` (`patient_id`),
    KEY `radiology_requests_doctor_id_foreign` (`doctor_id`),
    KEY `radiology_requests_status_index` (`status`),
    KEY `idx_rad_req_doctor_status` (`doctor_id`, `status`),
    CONSTRAINT `radiology_requests_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `radiology_requests_doctor_id_foreign`  FOREIGN KEY (`doctor_id`)  REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `radiology_images` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `radiology_request_id`  BIGINT UNSIGNED NOT NULL,
    `file_path`             VARCHAR(255)    NOT NULL,
    `file_name`             VARCHAR(255)    NOT NULL,
    `file_type`             VARCHAR(20)     NULL DEFAULT NULL,
    `file_size`             BIGINT UNSIGNED NULL DEFAULT NULL,
    `uploaded_by`           BIGINT UNSIGNED NOT NULL,
    `uploaded_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`                 TEXT            NULL,
    `created_at`            TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `radiology_images_radiology_request_id_foreign` (`radiology_request_id`),
    KEY `radiology_images_uploaded_by_index` (`uploaded_by`),
    CONSTRAINT `radiology_images_radiology_request_id_foreign` FOREIGN KEY (`radiology_request_id`) REFERENCES `radiology_requests` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `radiology_images_uploaded_by_foreign`           FOREIGN KEY (`uploaded_by`)           REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `radiology_reports` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `radiology_request_id`  BIGINT UNSIGNED NOT NULL,
    `radiologist_id`        BIGINT UNSIGNED NOT NULL,
    `findings`              TEXT            NOT NULL,
    `impression`            TEXT            NOT NULL,
    `recommendations`       TEXT            NULL,
    `status`                ENUM('Draft','Approved','Released') NOT NULL DEFAULT 'Draft',
    `approved_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `released_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_at`           TIMESTAMP       NULL DEFAULT NULL,
    `released_at`           TIMESTAMP       NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `radiology_reports_radiology_request_id_unique` (`radiology_request_id`),
    KEY `radiology_reports_radiologist_id_index` (`radiologist_id`),
    KEY `radiology_reports_approved_by_index` (`approved_by`),
    KEY `radiology_reports_released_by_index` (`released_by`),
    CONSTRAINT `radiology_reports_radiology_request_id_foreign` FOREIGN KEY (`radiology_request_id`) REFERENCES `radiology_requests` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `radiology_reports_radiologist_id_foreign`        FOREIGN KEY (`radiologist_id`)        REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `radiology_reports_approved_by_foreign`           FOREIGN KEY (`approved_by`)           REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `radiology_reports_released_by_foreign`           FOREIGN KEY (`released_by`)           REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- PMS — PHARMACY MANAGEMENT SYSTEM
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prescription_no` VARCHAR(255)    NOT NULL,
    `patient_id`      BIGINT UNSIGNED NOT NULL,
    `doctor_id`       BIGINT UNSIGNED NOT NULL,
    `status`          ENUM('Pending','Verified','Partially Dispensed','Dispensed','Cancelled') NOT NULL DEFAULT 'Pending',
    `notes`           TEXT            NULL,
    `diagnosis`       VARCHAR(255)    NULL DEFAULT NULL,
    `prescribed_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `verified_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `verified_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `prescriptions_prescription_no_unique` (`prescription_no`),
    KEY `prescriptions_patient_id_foreign` (`patient_id`),
    KEY `prescriptions_doctor_id_foreign` (`doctor_id`),
    KEY `prescriptions_status_index` (`status`),
    KEY `prescriptions_verified_by_index` (`verified_by`),
    KEY `idx_rx_doctor_status` (`doctor_id`, `status`),
    CONSTRAINT `prescriptions_patient_id_foreign`  FOREIGN KEY (`patient_id`)  REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `prescriptions_doctor_id_foreign`   FOREIGN KEY (`doctor_id`)   REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `prescriptions_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescription_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prescription_id` BIGINT UNSIGNED NOT NULL,
    `medication_name` VARCHAR(255)    NOT NULL,
    `dosage`          VARCHAR(255)    NOT NULL,
    `route`           VARCHAR(255)    NULL DEFAULT NULL,
    `frequency`       VARCHAR(255)    NOT NULL,
    `duration`        VARCHAR(255)    NOT NULL,
    `quantity`        INT             NOT NULL,
    `instructions`    TEXT            NULL,
    `status`          ENUM('Pending','Dispensed') NOT NULL DEFAULT 'Pending',
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `prescription_items_prescription_id_foreign` (`prescription_id`),
    KEY `prescription_items_status_index` (`status`),
    CONSTRAINT `prescription_items_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dispensing_records` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prescription_item_id`  BIGINT UNSIGNED NOT NULL,
    `pharmacist_id`         BIGINT UNSIGNED NOT NULL,
    `quantity_dispensed`    INT             NOT NULL,
    `lot_number`           VARCHAR(255)    NULL DEFAULT NULL,
    `expiry_date`          DATE            NULL DEFAULT NULL,
    `notes`                TEXT            NULL,
    `dispensed_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`           TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `dispensing_records_prescription_item_id_foreign` (`prescription_item_id`),
    KEY `dispensing_records_pharmacist_id_index` (`pharmacist_id`),
    CONSTRAINT `dispensing_records_prescription_item_id_foreign` FOREIGN KEY (`prescription_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `dispensing_records_pharmacist_id_foreign`         FOREIGN KEY (`pharmacist_id`)         REFERENCES `users` (`id`)              ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- SORS — SURGICAL & OPERATING ROOM SYSTEM
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `operating_rooms` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)    NOT NULL,
    `location`   VARCHAR(255)    NULL DEFAULT NULL,
    `status`     ENUM('Available','Occupied','Under Maintenance') NOT NULL DEFAULT 'Available',
    `equipment`  TEXT            NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    `updated_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `operating_rooms_name_unique` (`name`),
    KEY `operating_rooms_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgery_requests` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_no`         VARCHAR(255)    NOT NULL,
    `patient_id`         BIGINT UNSIGNED NOT NULL,
    `doctor_id`          BIGINT UNSIGNED NOT NULL,
    `procedure_name`     VARCHAR(255)    NOT NULL,
    `diagnosis`          VARCHAR(255)    NULL DEFAULT NULL,
    `urgency`            ENUM('Elective','Urgent','Emergency') NOT NULL DEFAULT 'Elective',
    `status`             ENUM('Pending','Scheduled','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    `notes`              TEXT            NULL,
    `anesthesia_type`    VARCHAR(255)    NULL DEFAULT NULL,
    `estimated_duration` INT             NULL DEFAULT NULL,
    `requested_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`         TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `surgery_requests_request_no_unique` (`request_no`),
    KEY `surgery_requests_patient_id_foreign` (`patient_id`),
    KEY `surgery_requests_doctor_id_foreign` (`doctor_id`),
    KEY `surgery_requests_status_index` (`status`),
    KEY `idx_surg_req_doctor_status` (`doctor_id`, `status`),
    CONSTRAINT `surgery_requests_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `surgery_requests_doctor_id_foreign`  FOREIGN KEY (`doctor_id`)  REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgical_teams` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)    NOT NULL,
    `surgeon_id` BIGINT UNSIGNED NOT NULL,
    `notes`      TEXT            NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    `updated_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `surgical_teams_surgeon_id_index` (`surgeon_id`),
    CONSTRAINT `surgical_teams_surgeon_id_foreign` FOREIGN KEY (`surgeon_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgical_team_members` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `surgical_team_id` BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `role_in_team`     VARCHAR(255)    NOT NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `surgical_team_members_team_user_unique` (`surgical_team_id`, `user_id`),
    KEY `surgical_team_members_user_id_foreign` (`user_id`),
    CONSTRAINT `surgical_team_members_surgical_team_id_foreign` FOREIGN KEY (`surgical_team_id`) REFERENCES `surgical_teams` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `surgical_team_members_user_id_foreign`           FOREIGN KEY (`user_id`)           REFERENCES `users` (`id`)          ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgery_schedules` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `surgery_request_id`   BIGINT UNSIGNED NOT NULL,
    `operating_room_id`    BIGINT UNSIGNED NOT NULL,
    `surgical_team_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `scheduled_by`         BIGINT UNSIGNED NOT NULL,
    `scheduled_at`         TIMESTAMP       NOT NULL,
    `duration_minutes`     INT             NOT NULL DEFAULT 60,
    `status`               ENUM('Scheduled','In Progress','Completed','Cancelled','Postponed') NOT NULL DEFAULT 'Scheduled',
    `actual_start`              TIMESTAMP       NULL DEFAULT NULL,
    `actual_end`                TIMESTAMP       NULL DEFAULT NULL,
    `previous_readiness_status` VARCHAR(32)     NULL DEFAULT NULL,
    `readiness_status`          ENUM('Pending Prep','Preparing','Ready') NOT NULL DEFAULT 'Pending Prep',
    `readiness_notes`           TEXT            NULL,
    `readiness_updated_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `readiness_updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    `notes`                TEXT            NULL,
    `created_at`           TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `surgery_schedules_surgery_request_id_foreign` (`surgery_request_id`),
    KEY `surgery_schedules_operating_room_id_foreign` (`operating_room_id`),
    KEY `surgery_schedules_surgical_team_id_foreign` (`surgical_team_id`),
    KEY `surgery_schedules_scheduled_by_index` (`scheduled_by`),
    KEY `surgery_schedules_status_index` (`status`),
    KEY `idx_surg_sched_at_status` (`scheduled_at`, `status`),
    CONSTRAINT `surgery_schedules_surgery_request_id_foreign` FOREIGN KEY (`surgery_request_id`) REFERENCES `surgery_requests` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `surgery_schedules_operating_room_id_foreign`  FOREIGN KEY (`operating_room_id`)  REFERENCES `operating_rooms` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `surgery_schedules_surgical_team_id_foreign`   FOREIGN KEY (`surgical_team_id`)   REFERENCES `surgical_teams` (`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `surgery_schedules_scheduled_by_foreign`       FOREIGN KEY (`scheduled_by`)       REFERENCES `users` (`id`)            ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- DNMS — DIET & NUTRITION MANAGEMENT SYSTEM
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `diet_requests` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_no`        VARCHAR(255)    NOT NULL,
    `patient_id`        BIGINT UNSIGNED NOT NULL,
    `doctor_id`         BIGINT UNSIGNED NOT NULL,
    `diet_type`         VARCHAR(255)    NOT NULL,
    `allergies`         TEXT            NULL,
    `food_restrictions` TEXT            NULL,
    `clinical_notes`    TEXT            NULL,
    `status`            ENUM('Pending','Active','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    `requested_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `diet_requests_request_no_unique` (`request_no`),
    KEY `diet_requests_patient_id_foreign` (`patient_id`),
    KEY `diet_requests_doctor_id_foreign` (`doctor_id`),
    KEY `diet_requests_status_index` (`status`),
    KEY `idx_diet_req_doctor_status` (`doctor_id`, `status`),
    CONSTRAINT `diet_requests_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `diet_requests_doctor_id_foreign`  FOREIGN KEY (`doctor_id`)  REFERENCES `users` (`id`)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `diet_plans` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `diet_request_id` BIGINT UNSIGNED NOT NULL,
    `dietitian_id`    BIGINT UNSIGNED NOT NULL,
    `plan_details`    TEXT            NOT NULL,
    `assessment_notes` TEXT           NULL,
    `total_calories`  INT             NULL DEFAULT NULL,
    `protein_grams`   DECIMAL(8,2)    NULL DEFAULT NULL,
    `carb_grams`      DECIMAL(8,2)    NULL DEFAULT NULL,
    `fat_grams`       DECIMAL(8,2)    NULL DEFAULT NULL,
    `start_date`      DATE            NOT NULL,
    `end_date`        DATE            NULL DEFAULT NULL,
    `status`          ENUM('Active','Completed','Revised') NOT NULL DEFAULT 'Active',
    `notes`           TEXT            NULL,
    `updated_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `completed_by`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `completed_at`    TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `diet_plans_diet_request_id_unique` (`diet_request_id`),
    KEY `diet_plans_dietitian_id_index` (`dietitian_id`),
    KEY `diet_plans_status_index` (`status`),
    CONSTRAINT `diet_plans_diet_request_id_foreign` FOREIGN KEY (`diet_request_id`) REFERENCES `diet_requests` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `diet_plans_dietitian_id_foreign`    FOREIGN KEY (`dietitian_id`)    REFERENCES `users` (`id`)          ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meal_schedules` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `diet_plan_id` BIGINT UNSIGNED NOT NULL,
    `meal_type`    ENUM('Breakfast','Mid-Morning Snack','Lunch','Afternoon Snack','Dinner','Bedtime Snack') NOT NULL,
    `meal_date`    DATE            NOT NULL,
    `menu`         TEXT            NOT NULL,
    `calories`     INT             NULL DEFAULT NULL,
    `is_served`    TINYINT(1)      NOT NULL DEFAULT 0,
    `notes`        TEXT            NULL,
    `created_at`   TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `meal_schedules_diet_plan_id_foreign` (`diet_plan_id`),
    CONSTRAINT `meal_schedules_diet_plan_id_foreign` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- MESSAGING & NOTIFICATIONS
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `type`        VARCHAR(50)     NOT NULL DEFAULT 'general',
    `title`       VARCHAR(255)    NOT NULL,
    `message`     TEXT            NOT NULL,
    `module`      VARCHAR(50)     NOT NULL DEFAULT 'clinical',
    `target_url`  VARCHAR(255)    NULL DEFAULT NULL,
    `priority`    ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
    `is_read`     TINYINT(1)      NOT NULL DEFAULT 0,
    `read_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `notifications_user_id_is_read_created_at_index` (`user_id`, `is_read`, `created_at`),
    CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`           VARCHAR(255)    NULL DEFAULT NULL,
    `created_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `last_message_at` TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `conversations_created_by_foreign` (`created_by`),
    CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_participants` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `last_read_at`    TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`, `user_id`),
    KEY `conversation_participants_user_id_foreign` (`user_id`),
    CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `conversation_participants_user_id_foreign`         FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`)         ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender_id`       BIGINT UNSIGNED NOT NULL,
    `message`         TEXT            NOT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `messages_conversation_id_created_at_index` (`conversation_id`, `created_at`),
    KEY `messages_sender_id_foreign` (`sender_id`),
    CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `messages_sender_id_foreign`       FOREIGN KEY (`sender_id`)       REFERENCES `users` (`id`)         ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- AUDIT TRAIL
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `action`         VARCHAR(255)    NOT NULL,
    `module`         VARCHAR(255)    NOT NULL,
    `description`    TEXT            NULL,
    `loggable_type`  VARCHAR(255)    NULL DEFAULT NULL,
    `loggable_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`     VARCHAR(45)     NULL DEFAULT NULL,
    `severity`       VARCHAR(20)     NOT NULL DEFAULT 'INFO',
    `result`         VARCHAR(20)     NULL DEFAULT NULL,
    `logged_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `activity_logs_loggable_type_loggable_id_index` (`loggable_type`, `loggable_id`),
    KEY `idx_activity_user_created` (`user_id`, `created_at`),
    KEY `idx_activity_module_severity` (`module`, `severity`),
    CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- MEDISENSE AI CLINICAL DECISION SUPPORT AUDIT
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `medisense_interactions` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`        BIGINT UNSIGNED NULL,
    `patient_id`     BIGINT UNSIGNED NULL,
    `task`           VARCHAR(100) NOT NULL,
    `provider`       VARCHAR(100) NOT NULL,
    `status`         VARCHAR(50) NOT NULL,
    `result_summary` TEXT NULL,
    `ip_address`     VARCHAR(45) NULL,
    `logged_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`     TIMESTAMP NULL,
    `updated_at`     TIMESTAMP NULL,
    CONSTRAINT `medisense_interactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `medisense_interactions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
    INDEX `idx_medisense_user` (`user_id`),
    INDEX `idx_medisense_patient` (`patient_id`),
    INDEX `idx_medisense_task` (`task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- OTP TWO-FACTOR AUTHENTICATION
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `purpose`      VARCHAR(50)     NOT NULL DEFAULT 'login'
                   COMMENT 'Intended use of this OTP (e.g. login, password_reset)',
    `otp_hash`     VARCHAR(255)    NOT NULL
                   COMMENT 'Bcrypt hash of the plaintext OTP — never store the OTP itself',
    `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0
                   COMMENT 'Number of failed verification attempts against this OTP',
    `last_sent_at` TIMESTAMP       NULL DEFAULT NULL
                   COMMENT 'Timestamp of last OTP generation/resend for cooldown enforcement',
    `expires_at`   TIMESTAMP       NOT NULL
                   COMMENT 'Absolute expiry of this OTP — expired OTPs must never be accepted',
    `verified_at`  TIMESTAMP       NULL DEFAULT NULL
                   COMMENT 'Set when OTP is successfully verified — prevents replay',
    `created_at`   TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_otp_user_purpose`  (`user_id`, `purpose`),
    KEY `idx_otp_expires`       (`expires_at`),
    CONSTRAINT `otp_verifications_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════════════════
-- SYSTEM SETTINGS (typed, scoped, JSON-encoded; NEVER holds secrets)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100)    NOT NULL,
    `scope`       VARCHAR(20)     NOT NULL COMMENT 'super_admin | admin',
    `value`       LONGTEXT        NULL COMMENT 'JSON-encoded typed value. Never a secret.',
    `updated_by`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `system_settings_key_unique` (`setting_key`),
    KEY `system_settings_updated_by_index` (`updated_by`),
    CONSTRAINT `system_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `setting_histories` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100)    NOT NULL,
    `scope`       VARCHAR(20)     NOT NULL,
    `old_value`   LONGTEXT        NULL,
    `new_value`   LONGTEXT        NULL,
    `changed_by`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `reason`      VARCHAR(500)    NULL DEFAULT NULL,
    `ip_address`  VARCHAR(45)     NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `setting_histories_key_index` (`setting_key`, `created_at`),
    KEY `setting_histories_changed_by_index` (`changed_by`),
    CONSTRAINT `setting_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- MASTER CATALOG (service definitions + lifecycle/version history)
-- NOTE: catalog entries are DEFINITIONS. They are not prescriptions, orders,
--       or completed results; those remain in their own module tables.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `catalog_services` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`           VARCHAR(30)     NOT NULL,
    `name`           VARCHAR(255)    NOT NULL,
    `module`         ENUM('LIS','RIS','PMS','SORS','DNMS') NOT NULL,
    `category`       VARCHAR(100)    NULL DEFAULT NULL,
    `description`    TEXT            NULL,
    `base_price`     DECIMAL(12,2)   NULL DEFAULT NULL,
    `status`         ENUM('draft','active','inactive','retired') NOT NULL DEFAULT 'draft',
    `effective_from` DATE            NULL DEFAULT NULL,
    `effective_to`   DATE            NULL DEFAULT NULL,
    `version`        INT UNSIGNED    NOT NULL DEFAULT 1,
    `created_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `catalog_services_code_unique` (`code`),
    KEY `catalog_services_module_status_index` (`module`, `status`),
    CONSTRAINT `catalog_services_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `catalog_services_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lifecycle metadata for existing `lab_tests` rows, kept in a side table so the
-- live LIS tables are NOT altered.
CREATE TABLE IF NOT EXISTS `catalog_item_states` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`    VARCHAR(30)     NOT NULL COMMENT 'lab_test',
    `entity_id`      BIGINT UNSIGNED NOT NULL,
    `status`         ENUM('active','inactive','retired') NOT NULL DEFAULT 'active',
    `description`    TEXT            NULL,
    `effective_from` DATE            NULL DEFAULT NULL,
    `effective_to`   DATE            NULL DEFAULT NULL,
    `version`        INT UNSIGNED    NOT NULL DEFAULT 1,
    `retired_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `catalog_item_states_entity_unique` (`entity_type`, `entity_id`),
    CONSTRAINT `catalog_item_states_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_change_histories` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`     VARCHAR(30)     NOT NULL COMMENT 'lab_test | service',
    `entity_id`       BIGINT UNSIGNED NOT NULL,
    `version`         INT UNSIGNED    NOT NULL,
    `action`          VARCHAR(30)     NOT NULL COMMENT 'created | updated | activated | deactivated | retired',
    `before_snapshot` LONGTEXT        NULL,
    `after_snapshot`  LONGTEXT        NULL,
    `reason`          VARCHAR(500)    NULL DEFAULT NULL,
    `changed_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `catalog_change_histories_entity_index` (`entity_type`, `entity_id`, `version`),
    CONSTRAINT `catalog_change_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- CLINICAL CODE MASTER (ICD / CPT / LOINC) — import framework only.
-- NO official codes are seeded. Datasets must be obtained under licence.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `clinical_code_systems` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `system_key`          VARCHAR(20)     NOT NULL COMMENT 'ICD-11 | CPT | LOINC',
    `name`                VARCHAR(150)    NOT NULL,
    `kind`                ENUM('diagnosis','procedure','observation') NOT NULL,
    `license_status`      ENUM('unverified','verified') NOT NULL DEFAULT 'unverified',
    `license_reference`   VARCHAR(255)    NULL DEFAULT NULL,
    `license_notes`       TEXT            NULL,
    `jurisdiction`        VARCHAR(100)    NULL DEFAULT NULL,
    `license_verified_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `license_verified_at` TIMESTAMP       NULL DEFAULT NULL,
    `active_version_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `clinical_code_systems_key_unique` (`system_key`),
    CONSTRAINT `clinical_code_systems_verified_by_foreign` FOREIGN KEY (`license_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clinical_code_versions` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `system_id`         BIGINT UNSIGNED NOT NULL,
    `version_label`     VARCHAR(50)     NOT NULL,
    `effective_date`    DATE            NULL DEFAULT NULL,
    `status`            ENUM('staged','published','superseded','rolled_back') NOT NULL DEFAULT 'staged',
    `source_filename`   VARCHAR(255)    NOT NULL,
    `source_sha256`     CHAR(64)        NOT NULL,
    `total_rows`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `valid_rows`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `invalid_rows`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `duplicate_rows`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `validation_report` LONGTEXT        NULL,
    `imported_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `published_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `published_at`      TIMESTAMP       NULL DEFAULT NULL,
    `rolled_back_at`    TIMESTAMP       NULL DEFAULT NULL,
    `rollback_reason`   VARCHAR(500)    NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `clinical_code_versions_system_label_unique` (`system_id`, `version_label`),
    KEY `clinical_code_versions_sha_index` (`system_id`, `source_sha256`),
    CONSTRAINT `clinical_code_versions_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `clinical_code_systems` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `clinical_code_versions_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `clinical_code_versions_published_by_foreign` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clinical_codes` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version_id` BIGINT UNSIGNED NOT NULL,
    `system_id`  BIGINT UNSIGNED NOT NULL,
    `code`       VARCHAR(40)     NOT NULL,
    `display`    VARCHAR(500)    NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `clinical_codes_version_code_unique` (`version_id`, `code`),
    KEY `clinical_codes_system_code_index` (`system_id`, `code`),
    CONSTRAINT `clinical_codes_version_id_foreign` FOREIGN KEY (`version_id`) REFERENCES `clinical_code_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `clinical_codes_system_id_foreign`  FOREIGN KEY (`system_id`)  REFERENCES `clinical_code_systems` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- BACKUP JOB HISTORY
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `backup_runs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trigger_type`    ENUM('manual','scheduled','cli') NOT NULL DEFAULT 'manual',
    `status`          ENUM('queued','running','success','failed') NOT NULL DEFAULT 'queued',
    `file_path`       VARCHAR(500)    NULL DEFAULT NULL COMMENT 'Relative to the private local disk. Not web-accessible.',
    `file_size`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `checksum_sha256` CHAR(64)        NULL DEFAULT NULL,
    `started_at`      TIMESTAMP       NULL DEFAULT NULL,
    `finished_at`     TIMESTAMP       NULL DEFAULT NULL,
    `error_summary`   VARCHAR(1000)   NULL DEFAULT NULL,
    `pruned_at`       TIMESTAMP       NULL DEFAULT NULL,
    `triggered_by`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `backup_runs_status_index` (`status`, `created_at`),
    CONSTRAINT `backup_runs_triggered_by_foreign` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- PRIVILEGED ACCESS REQUESTS (e.g. Admin asks Super Admin to authorise a Doctor)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `privileged_access_requests` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_type`     VARCHAR(40)     NOT NULL COMMENT 'doctor_provisioning',
    `payload`          LONGTEXT        NULL COMMENT 'JSON, non-secret',
    `status`           ENUM('pending','approved','rejected','failed') NOT NULL DEFAULT 'pending',
    `requested_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `requested_reason` VARCHAR(500)    NULL DEFAULT NULL,
    `decided_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `decided_at`       TIMESTAMP       NULL DEFAULT NULL,
    `decision_reason`  VARCHAR(500)    NULL DEFAULT NULL,
    `result_user_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `privileged_access_requests_status_index` (`status`, `created_at`),
    CONSTRAINT `par_requested_by_foreign`  FOREIGN KEY (`requested_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `par_decided_by_foreign`    FOREIGN KEY (`decided_by`)     REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `par_result_user_id_foreign` FOREIGN KEY (`result_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- FINANCIAL CONFIGURATION FOUNDATION (no billing module exists yet)
-- Effective-dated and immutable: a new rule is a NEW row; old rows are never
-- rewritten, so historical documents can always be re-derived.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `financial_rules` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `currency_code`       CHAR(3)         NOT NULL,
    `currency_symbol`     VARCHAR(8)      NOT NULL,
    `tax_name`            VARCHAR(60)     NULL DEFAULT NULL,
    `tax_rate`            DECIMAL(7,4)    NOT NULL DEFAULT 0.0000,
    `service_charge_rate` DECIMAL(7,4)    NOT NULL DEFAULT 0.0000,
    `applies_to`          ENUM('none','selected_services') NOT NULL DEFAULT 'none',
    `notes`               VARCHAR(500)    NULL DEFAULT NULL,
    `effective_from`      DATE            NOT NULL,
    `created_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `financial_rules_effective_from_index` (`effective_from`),
    CONSTRAINT `financial_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- QUEUE TICKET FOUNDATION (no ticket generation exists yet)
-- Atomic per-day sequence; unique ticket numbers enforced by the database.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `queue_sequences` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_type`  VARCHAR(20)     NOT NULL COMMENT 'diagnostic | treatment',
    `ticket_date` DATE            NOT NULL,
    `last_number` INT UNSIGNED    NOT NULL DEFAULT 0,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `queue_sequences_type_date_unique` (`queue_type`, `ticket_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_tickets` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_type`  VARCHAR(20)     NOT NULL,
    `ticket_date` DATE            NOT NULL,
    `sequence`    INT UNSIGNED    NOT NULL,
    `prefix`      VARCHAR(10)     NOT NULL COMMENT 'Prefix as issued; later prefix changes never rewrite this',
    `ticket_no`   VARCHAR(32)     NOT NULL,
    `issued_by`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `queue_tickets_type_date_seq_unique` (`queue_type`, `ticket_date`, `sequence`),
    UNIQUE KEY `queue_tickets_date_no_unique` (`ticket_date`, `ticket_no`),
    CONSTRAINT `queue_tickets_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════
-- OUTPATIENT APPOINTMENTS & SCHEDULING ENGINE
-- ════════════════════════════════════════════════════════════════════════════

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

-- ════════════════════════════════════════════════════════════════════════════
-- OPERATIONAL INVOICING & BILLING LEDGER
-- ════════════════════════════════════════════════════════════════════════════

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

-- ════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 1;

