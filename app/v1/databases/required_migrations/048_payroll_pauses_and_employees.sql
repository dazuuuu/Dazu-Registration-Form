-- Migration 048: Salary pauses (student and tutor breaks), reassignment carry-over, and employees.

-- Months of the student's course already paid for under other tutors or earlier stints. A student who is moved
-- to a new tutor only earns that tutor the months that are left, so the course is never paid for twice.
ALTER TABLE `tutor_students` ADD COLUMN `months_offset` TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Frozen record of the months a tutor earned for a student who was taken off. earned_months is a comma list of YYYY-MM.
ALTER TABLE `tutor_payroll_history` ADD COLUMN `registration_id` INT NULL DEFAULT NULL;
ALTER TABLE `tutor_payroll_history` ADD COLUMN `earned_months` TEXT NULL DEFAULT NULL;

-- Periods where salary stops. user_id NULL means the whole tutor is away, otherwise it is one student.
-- end_date is the day salary resumes (NULL while the pause is still running).
CREATE TABLE IF NOT EXISTS `tutor_pauses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL,
  `user_id` INT NULL DEFAULT NULL,
  `kind` ENUM('pause','break','holiday') NOT NULL DEFAULT 'pause',
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tutor_pauses_tutor` (`tutor_id`, `user_id`),
  CONSTRAINT `fk_tutor_pauses_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employees who are not tutors. They sign in through the normal login page and land on the employee dashboard.
INSERT INTO `roles` (`role_name`) VALUES ('employee') ON DUPLICATE KEY UPDATE `role_name` = `role_name`;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `job_title` VARCHAR(100) NOT NULL,
  `monthly_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `pay_day` TINYINT UNSIGNED NOT NULL DEFAULT 28,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `phone` VARCHAR(30) NULL DEFAULT NULL,
  `bio` TEXT NULL,
  `photo_path` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `credentials_sent_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employees_user` (`user_id`),
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `paid_for_month` DATE NOT NULL,
  `paid_on` DATE NOT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `recorded_by` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_payments_employee` (`employee_id`, `paid_for_month`),
  CONSTRAINT `fk_employee_payments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_payments_admin` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
