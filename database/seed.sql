-- ============================================================
-- MotoLease Pro — Seed Data
-- Run AFTER schema.sql
-- Default admin: admin@motolease.com / Admin@1234
-- ============================================================

USE `moto_lease_db`;

-- ===== Default Admin User =====
-- Password: Admin@1234  (bcrypt hash below)
-- To regenerate the hash for a different password, run in PHP:
--   echo password_hash('YourPassword', PASSWORD_BCRYPT);
--
-- The hash below is for the password:  Admin@1234
INSERT IGNORE INTO `users` (`name`, `email`, `password_hash`, `role`, `is_active`) VALUES
('Administrator', 'admin@motolease.com',
 '$2y$10$v.bZ1FpH9FrK2Jz0vQ6ZOuv7LFxPwEHaOJO8kUx8t1.WXJL/Qk2vu',
 'admin', 1);

-- NOTE: The hash above uses the standard bcrypt cost=10.
-- To generate a fresh hash, run in PHP:
--   echo password_hash('YourPassword', PASSWORD_BCRYPT);

-- ===== Sample Leasing Plans =====
INSERT IGNORE INTO `leasing_plans` (`name`, `duration_months`, `markup_percent`, `is_active`) VALUES
('3-Month Plan',   3,  5.00, 1),
('6-Month Plan',   6,  10.00, 1),
('12-Month Plan',  12, 15.00, 1),
('18-Month Plan',  18, 20.00, 1),
('24-Month Plan',  24, 25.00, 1);

-- ===== Sample Motorcycles =====
INSERT IGNORE INTO `motorcycles` (`name`, `model`, `price`, `status`) VALUES
('Honda CD 70',    'CD70-2024',     95000.00,  'available'),
('Honda CG 125',   'CG125-2024',   175000.00,  'available'),
('Yamaha YBR 125', 'YBR125G-2024', 225000.00,  'available'),
('Honda CB 150F',  'CB150F-2024',  305000.00,  'available'),
('Suzuki GS 150',  'GS150R-2024',  245000.00,  'available'),
('United 100cc',   'US100-2024',    79000.00,  'available'),
('Road Prince 70', 'RP70-2024',     72000.00,  'available'),
('Ravi Piaggio',   'RP4S-2024',    145000.00,  'available');
