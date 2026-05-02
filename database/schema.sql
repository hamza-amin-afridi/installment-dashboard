-- ============================================================
-- Motorcycle Leasing Management Portal — Database Schema
-- Engine: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:00";

-- Create database (update name as needed)
CREATE DATABASE IF NOT EXISTS `oneclick2trip_installment`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `oneclick2trip_installment`;

-- ============================================================
-- Table: users (Admin accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100)    NOT NULL,
  `email`          VARCHAR(150)    NOT NULL UNIQUE,
  `password_hash`  VARCHAR(255)    NOT NULL,
  `role`           ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  `reset_token`    VARCHAR(100)    NULL DEFAULT NULL,
  `reset_expiry`   DATETIME        NULL DEFAULT NULL,
  `last_login`     DATETIME        NULL DEFAULT NULL,
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: motorcycles
-- ============================================================
CREATE TABLE IF NOT EXISTS `motorcycles` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `model`       VARCHAR(100)    NOT NULL,
  `price`       DECIMAL(12,2)   NOT NULL,
  `image_path`  VARCHAR(255)    NULL DEFAULT NULL,
  `status`      ENUM('available','leased','retired') NOT NULL DEFAULT 'available',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leasing_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS `leasing_plans` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100)    NOT NULL,
  `duration_months` INT UNSIGNED    NOT NULL,
  `markup_percent`  DECIMAL(5,2)    NOT NULL,
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: customers
-- ============================================================
CREATE TABLE IF NOT EXISTS `customers` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `cnic`        VARCHAR(15)     NOT NULL UNIQUE,
  `phone`       VARCHAR(20)     NOT NULL,
  `address`     TEXT            NOT NULL,
  `score`       ENUM('good','average','risky') NOT NULL DEFAULT 'good',
  `score_value` INT             NOT NULL DEFAULT 100,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cnic` (`cnic`),
  INDEX `idx_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: guarantors (up to 2 per customer)
-- ============================================================
CREATE TABLE IF NOT EXISTS `guarantors` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `customer_id`  INT UNSIGNED    NOT NULL,
  `name`         VARCHAR(150)    NOT NULL,
  `cnic`         VARCHAR(15)     NOT NULL,
  `phone`        VARCHAR(20)     NOT NULL,
  `address`      TEXT            NOT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_customer` (`customer_id`),
  CONSTRAINT `fk_guarantor_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leases
-- ============================================================
CREATE TABLE IF NOT EXISTS `leases` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `customer_id`     INT UNSIGNED    NOT NULL,
  `motorcycle_id`   INT UNSIGNED    NOT NULL,
  `plan_id`         INT UNSIGNED    NOT NULL,
  `total_amount`    DECIMAL(12,2)   NOT NULL,
  `paid_amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `monthly_install` DECIMAL(12,2)   NOT NULL,
  `start_date`      DATE            NOT NULL,
  `end_date`        DATE            NOT NULL,
  `next_due_date`   DATE            NOT NULL,
  `status`          ENUM('active','completed','defaulted') NOT NULL DEFAULT 'active',
  `notes`           TEXT            NULL DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_motorcycle` (`motorcycle_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_due_date` (`next_due_date`),
  CONSTRAINT `fk_lease_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lease_motorcycle`
    FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lease_plan`
    FOREIGN KEY (`plan_id`) REFERENCES `leasing_plans` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: payments
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `lease_id`      INT UNSIGNED    NOT NULL,
  `amount`        DECIMAL(12,2)   NOT NULL,
  `payment_date`  DATE            NOT NULL,
  `receipt_no`    VARCHAR(30)     NOT NULL UNIQUE,
  `recorded_by`   INT UNSIGNED    NULL DEFAULT NULL,
  `notes`         TEXT            NULL DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lease` (`lease_id`),
  INDEX `idx_date` (`payment_date`),
  CONSTRAINT `fk_payment_lease`
    FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_user`
    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: activity_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NULL DEFAULT NULL,
  `user_name`    VARCHAR(100)    NOT NULL DEFAULT 'System',
  `action`       VARCHAR(255)    NOT NULL,
  `target_type`  VARCHAR(50)     NULL DEFAULT NULL,
  `target_id`    INT UNSIGNED    NULL DEFAULT NULL,
  `ip_address`   VARCHAR(45)     NULL DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
