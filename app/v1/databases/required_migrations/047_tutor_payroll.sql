-- Migration 047: Tutor payroll.
-- monthly_rate on tutor_students: NULL means the default rate (KES 5,000 per student per month), a value overrides it for that student only.
ALTER TABLE `tutor_students` ADD COLUMN `monthly_rate` DECIMAL(10,2) NULL DEFAULT NULL;

-- Payments the admin records against a tutor. paid_for_month is always the first day of the month the payment counts against.
-- confirmed_at is set when the tutor confirms on their dashboard that they received it.
CREATE TABLE IF NOT EXISTS `tutor_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `paid_for_month` DATE NOT NULL,
  `paid_on` DATE NOT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `recorded_by` INT NULL DEFAULT NULL,
  `confirmed_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tutor_payments_tutor_month` (`tutor_id`, `paid_for_month`),
  CONSTRAINT `fk_tutor_payments_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tutor_payments_admin` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When a student is taken off a tutor, what the tutor already earned for that student is kept here so it is not lost.
CREATE TABLE IF NOT EXISTS `tutor_payroll_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `student_name` VARCHAR(150) NULL DEFAULT NULL,
  `course_title` VARCHAR(150) NULL DEFAULT NULL,
  `abbreviation` VARCHAR(10) NULL DEFAULT NULL,
  `duration_months` TINYINT UNSIGNED NULL DEFAULT NULL,
  `weeks_of_study` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `days_of_study` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `monthly_rate` DECIMAL(10,2) NULL DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL,
  `removed_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tutor_payroll_history_tutor` (`tutor_id`),
  CONSTRAINT `fk_tutor_payroll_history_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
