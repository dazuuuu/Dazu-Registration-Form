-- Default Mon–Fri class schedules + partners section for homepage.

CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NULL DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partners_active_sort (is_active, sort_order)
) ENGINE=InnoDB;

-- Seed default schedules only when the table is empty.
INSERT INTO class_schedule_entries (study_mode, day_label, time_label, notes, sort_order, is_active)
SELECT study_mode, day_label, time_label, notes, sort_order, is_active FROM (
    SELECT 'online' AS study_mode, 'Monday – Friday' AS day_label, '6:00 AM – 8:00 AM' AS time_label, 'Morning online session' AS notes, 1 AS sort_order, 1 AS is_active
    UNION ALL SELECT 'online', 'Monday – Friday', '9:00 AM – 11:00 AM', 'Morning online session', 2, 1
    UNION ALL SELECT 'online', 'Monday – Friday', '12:00 PM – 2:00 PM', 'Afternoon online session', 3, 1
    UNION ALL SELECT 'online', 'Monday – Friday', '3:00 PM – 5:00 PM', 'Afternoon online session', 4, 1
    UNION ALL SELECT 'online', 'Monday – Friday', '6:00 PM – 8:00 PM', 'Evening online session', 5, 1
    UNION ALL SELECT 'physical', 'Monday – Friday', '6:00 PM – 8:00 PM', 'Campus lab (Room 529)', 1, 1
    UNION ALL SELECT 'physical', 'Saturday', '9:00 AM – 1:00 PM', 'Weekend campus session', 2, 1
) AS defaults
WHERE (SELECT COUNT(*) FROM class_schedule_entries) = 0;
