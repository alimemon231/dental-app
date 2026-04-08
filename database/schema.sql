-- ============================================================
-- DENTAL APP — DATABASE SCHEMA
-- Run this once to create all tables.
-- Database: dental_app
-- ============================================================

CREATE DATABASE IF NOT EXISTS `dental_app`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `dental_app`;

-- ----------------------------------------------------------------
-- USERS (staff / doctors / admin — no public registration)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `first_name`    VARCHAR(80)     NOT NULL,
  `last_name`     VARCHAR(80)     NOT NULL,
  `email`         VARCHAR(180)    NOT NULL UNIQUE,
  `password`      VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','doctor','receptionist','pharmacist','accountant') NOT NULL DEFAULT 'receptionist',
  `phone`         VARCHAR(30)     DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login`    DATETIME        DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_email`  (`email`),
  INDEX `idx_role`   (`role`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PASSWORD RESET TOKENS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`       VARCHAR(180) NOT NULL,
  `code`        VARCHAR(255) NOT NULL,  -- bcrypt hash of 6-digit code
  `expires_at`  DATETIME     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PATIENTS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patients` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `patient_code`  VARCHAR(20)     UNIQUE DEFAULT NULL,   -- e.g. PAT-00001
  `first_name`    VARCHAR(80)     NOT NULL,
  `last_name`     VARCHAR(80)     NOT NULL,
  `gender`        ENUM('male','female','other') DEFAULT NULL,
  `date_of_birth` DATE            DEFAULT NULL,
  `phone`         VARCHAR(30)     DEFAULT NULL,
  `email`         VARCHAR(180)    DEFAULT NULL,
  `address`       TEXT            DEFAULT NULL,
  `city`          VARCHAR(80)     DEFAULT NULL,
  `blood_group`   VARCHAR(5)      DEFAULT NULL,
  `allergies`     TEXT            DEFAULT NULL,
  `notes`         TEXT            DEFAULT NULL,
  `referred_by`   VARCHAR(120)    DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_name`  (`first_name`, `last_name`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_code`  (`patient_code`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- APPOINTMENTS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`       INT UNSIGNED NOT NULL,
  `doctor_id`        INT UNSIGNED DEFAULT NULL,
  `appointment_date` DATE         NOT NULL,
  `appointment_time` TIME         NOT NULL,
  `duration_minutes` INT          NOT NULL DEFAULT 30,
  `status`           ENUM('pending','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `type`             VARCHAR(80)  DEFAULT NULL,         -- e.g. 'Check-up', 'Extraction'
  `chief_complaint`  TEXT         DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `created_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_patient`    (`patient_id`),
  INDEX `idx_doctor`     (`doctor_id`),
  INDEX `idx_date`       (`appointment_date`),
  INDEX `idx_status`     (`status`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`)  REFERENCES `users`(`id`)    ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- TREATMENTS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `treatments` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `patient_id`     INT UNSIGNED    NOT NULL,
  `appointment_id` INT UNSIGNED    DEFAULT NULL,
  `doctor_id`      INT UNSIGNED    DEFAULT NULL,
  `treatment_date` DATE            NOT NULL,
  `procedure_name` VARCHAR(150)    NOT NULL,
  `tooth_number`   VARCHAR(20)     DEFAULT NULL,
  `description`    TEXT            DEFAULT NULL,
  `cost`           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `status`         ENUM('planned','in_progress','completed','on_hold') NOT NULL DEFAULT 'planned',
  `notes`          TEXT            DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_patient`     (`patient_id`),
  INDEX `idx_appointment` (`appointment_id`),
  FOREIGN KEY (`patient_id`)     REFERENCES `patients`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_id`)      REFERENCES `users`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PRESCRIPTIONS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`     INT UNSIGNED NOT NULL,
  `doctor_id`      INT UNSIGNED DEFAULT NULL,
  `appointment_id` INT UNSIGNED DEFAULT NULL,
  `prescribed_on`  DATE         NOT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`)     REFERENCES `patients`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`)      REFERENCES `users`(`id`)        ON DELETE SET NULL,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescription_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_id` INT UNSIGNED NOT NULL,
  `medicine_name`   VARCHAR(150) NOT NULL,
  `dosage`          VARCHAR(80)  DEFAULT NULL,   -- e.g. '500mg'
  `frequency`       VARCHAR(80)  DEFAULT NULL,   -- e.g. 'Twice daily'
  `duration`        VARCHAR(80)  DEFAULT NULL,   -- e.g. '5 days'
  `instructions`    TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PRODUCT CATEGORIES
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL UNIQUE,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PRODUCTS / INVENTORY ITEMS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `sku`             VARCHAR(60)     UNIQUE DEFAULT NULL,
  `name`            VARCHAR(150)    NOT NULL,
  `category_id`     INT UNSIGNED    DEFAULT NULL,
  `description`     TEXT            DEFAULT NULL,
  `unit`            VARCHAR(30)     DEFAULT 'piece',  -- piece, box, ml, etc.
  `purchase_price`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `selling_price`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `quantity`        INT             NOT NULL DEFAULT 0,
  `reorder_level`   INT             NOT NULL DEFAULT 5,
  `expiry_date`     DATE            DEFAULT NULL,
  `supplier_id`     INT UNSIGNED    DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_sku`      (`sku`),
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- STOCK MOVEMENTS (IN / OUT log)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED    NOT NULL,
  `type`       ENUM('in','out','adjustment') NOT NULL,
  `quantity`   INT             NOT NULL,
  `reference`  VARCHAR(100)    DEFAULT NULL,  -- Invoice #, PO #, etc.
  `notes`      TEXT            DEFAULT NULL,
  `created_by` INT UNSIGNED    DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- SUPPLIERS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `contact`     VARCHAR(100) DEFAULT NULL,
  `phone`       VARCHAR(30)  DEFAULT NULL,
  `email`       VARCHAR(180) DEFAULT NULL,
  `address`     TEXT         DEFAULT NULL,
  `notes`       TEXT         DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `products` ADD CONSTRAINT `fk_product_supplier`
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL;

-- ----------------------------------------------------------------
-- INVOICES
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(30)     NOT NULL UNIQUE,   -- INV-20250001
  `patient_id`     INT UNSIGNED    NOT NULL,
  `appointment_id` INT UNSIGNED    DEFAULT NULL,
  `invoice_date`   DATE            NOT NULL,
  `due_date`       DATE            DEFAULT NULL,
  `subtotal`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `tax`            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `amount_paid`    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `balance`        DECIMAL(10,2)   GENERATED ALWAYS AS (`total` - `amount_paid`) STORED,
  `status`         ENUM('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `notes`          TEXT            DEFAULT NULL,
  `created_by`     INT UNSIGNED    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_patient` (`patient_id`),
  INDEX `idx_status`  (`status`),
  FOREIGN KEY (`patient_id`)     REFERENCES `patients`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- INVOICE ITEMS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED  NOT NULL,
  `description` VARCHAR(200)  NOT NULL,
  `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  INDEX `idx_invoice` (`invoice_id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- PAYMENTS
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `invoice_id`     INT UNSIGNED   DEFAULT NULL,
  `patient_id`     INT UNSIGNED   NOT NULL,
  `amount_paid`    DECIMAL(10,2)  NOT NULL,
  `payment_date`   DATE           NOT NULL,
  `payment_method` ENUM('cash','card','bank_transfer','cheque','other') NOT NULL DEFAULT 'cash',
  `reference`      VARCHAR(100)   DEFAULT NULL,
  `status`         ENUM('completed','pending','failed','refunded') NOT NULL DEFAULT 'completed',
  `notes`          TEXT           DEFAULT NULL,
  `created_by`     INT UNSIGNED   DEFAULT NULL,
  `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_invoice`      (`invoice_id`),
  INDEX `idx_patient`      (`patient_id`),
  INDEX `idx_payment_date` (`payment_date`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- SYSTEM SETTINGS (key-value store)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(100) NOT NULL UNIQUE,
  `value`      TEXT         DEFAULT NULL,
  `group`      VARCHAR(60)  DEFAULT 'general',
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_key`   (`key`),
  INDEX `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- SEED DATA
-- ----------------------------------------------------------------

-- Default admin user (password: Admin@1234)
INSERT IGNORE INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`, `status`) VALUES
('System', 'Admin', 'admin@dentalapp.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234 (bcrypt)
 'admin', 'active');

-- Product categories
INSERT IGNORE INTO `product_categories` (`name`) VALUES
('Anaesthetics'), ('Instruments'), ('Consumables'),
('Restorative Materials'), ('Impression Materials'), ('Medicines');

-- Default settings
INSERT IGNORE INTO `settings` (`key`, `value`, `group`) VALUES
('clinic_name',     'DentalPro Clinic',            'clinic'),
('clinic_phone',    '',                             'clinic'),
('clinic_email',    '',                             'clinic'),
('clinic_address',  '',                             'clinic'),
('currency_symbol', 'PKR ',                         'finance'),
('tax_rate',        '0',                            'finance'),
('invoice_prefix',  'INV',                          'finance'),
('low_stock_alert', '5',                            'inventory'),
('date_format',     'd M Y',                        'general');
