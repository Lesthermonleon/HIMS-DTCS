-- ============================================================================
-- DTCS Hospital Management System — User Accounts & Roles SQL Seeder

-- MySQL 8.x Compatible · Laravel Synchronized
-- Generated: 2026-08-16
-- Default Password for all seeded accounts: "password"
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. ROLES SEEDER
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `slug`, `dashboard_route`, `description`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator',    'admin',          'admin.dashboard',     'Full access to all modules and system settings',              NOW(), NOW()),
(2, 'Doctor',                  'doctor',         'doctor.dashboard',    'Order tests, prescriptions, surgeries, and diet requests',    NOW(), NOW()),
(3, 'Medical Technologist',    'med-tech',       'lab.dashboard',       'Receive and process laboratory requests, encode results',      NOW(), NOW()),
(4, 'Radiologic Technologist', 'rad-tech',       'radiology.dashboard', 'Perform imaging procedures and upload images',                 NOW(), NOW()),
(5, 'Radiologist',             'radiologist',    'radiology.dashboard', 'Interpret imaging results and approve radiology reports',     NOW(), NOW()),
(6, 'Pharmacist',              'pharmacist',     'pharmacy.dashboard',  'Verify and dispense prescriptions',                          NOW(), NOW()),
(7, 'Dietitian / Nutritionist','dietitian',      'diet.dashboard',      'Create and manage therapeutic diet plans',                   NOW(), NOW()),
(8, 'OR Coordinator',          'or-coordinator', 'surgery.dashboard',   'Schedule surgeries and assign operating rooms',               NOW(), NOW()),
(9, 'OR Staff',                'or-staff',       'surgery.dashboard',   'View assigned surgery schedules, update OR readiness and operational status', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `dashboard_route` = VALUES(`dashboard_route`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- 2. USERS SEEDER
-- Note: Password hash corresponds to 'password' (Laravel standard bcrypt hash)
-- ----------------------------------------------------------------------------
INSERT INTO `users` (
    `id`, `name`, `email`, `employee_id`, `department`, `phone`, `avatar`, 
    `is_active`, `password`, `created_at`, `updated_at`
) VALUES
(1, 'System Administrator', 'kirito.zoldyck75@gmail.com',       'EMP-0001', 'Administration',       '09000000001', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(2, 'Dr. Juan Dela Cruz',   'heike.hugo101@gmail.com',      'EMP-0002', 'Internal Medicine',    '09000000002', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(3, 'Maria Santos',         'medtech@dtcs.com',     'EMP-0003', 'Laboratory',           '09000000003', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(4, 'Jose Reyes',           'radtech@dtcs.com',     'EMP-0004', 'Radiology',            '09000000004', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(5, 'Dr. Ana Lim',          'radiologist@dtcs.com', 'EMP-0005', 'Radiology',            '09000000005', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(6, 'Pedro Garcia',         'pharmacist@dtcs.com',  'EMP-0006', 'Pharmacy',             '09000000006', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(7, 'Rosa Mendoza',         'dietitian@dtcs.com',   'EMP-0007', 'Nutrition & Dietetics','09000000007', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW()),
(8, 'Carlos Torres',        'orcoord@dtcs.com',     'EMP-0008', 'Operating Room',       '09000000008', NULL, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `employee_id` = VALUES(`employee_id`),
    `department` = VALUES(`department`),
    `phone` = VALUES(`phone`),
    `is_active` = VALUES(`is_active`),
    `password` = VALUES(`password`),
    `updated_at` = NOW();

-- ----------------------------------------------------------------------------
-- 3. ROLE_USER ASSIGNMENTS
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `role_user` (`user_id`, `role_id`) VALUES
(1, 1), -- Admin -> admin
(2, 2), -- Dr. Juan Dela Cruz -> doctor
(3, 3), -- Maria Santos -> med-tech
(4, 4), -- Jose Reyes -> rad-tech
(5, 5), -- Dr. Ana Lim -> radiologist
(6, 6), -- Pedro Garcia -> pharmacist
(7, 7), -- Rosa Mendoza -> dietitian
(8, 8); -- Carlos Torres -> or-coordinator

SET FOREIGN_KEY_CHECKS = 1;
