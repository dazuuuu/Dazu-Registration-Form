-- Migration 045: Direct tutor-to-student assignments (replaces course-based tutor assignment)
CREATE TABLE IF NOT EXISTS `tutor_students` (
  `tutor_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_id`, `user_id`),
  FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
