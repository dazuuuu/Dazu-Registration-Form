-- Timetables a tutor shares with all or chosen students: an uploaded PDF and/or a table of weekly classes.
CREATE TABLE IF NOT EXISTS `timetables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tutor_user_id` INT NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `pdf_path` VARCHAR(255) NULL,
  `audience` ENUM('all','selected') NOT NULL DEFAULT 'all',
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'Africa/Nairobi',
  `alert_minutes` SMALLINT NOT NULL DEFAULT 15,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tt_tutor` (`tutor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timetable_students` (
  `timetable_id` INT UNSIGNED NOT NULL,
  `student_user_id` INT NOT NULL,
  PRIMARY KEY (`timetable_id`, `student_user_id`),
  KEY `idx_tts_student` (`student_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timetable_slots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timetable_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT NOT NULL,
  `course` VARCHAR(160) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NULL,
  `venue` VARCHAR(160) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tsl_tt` (`timetable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timetable_alerts_sent` (
  `slot_id` INT UNSIGNED NOT NULL,
  `class_date` DATE NOT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slot_id`, `class_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
