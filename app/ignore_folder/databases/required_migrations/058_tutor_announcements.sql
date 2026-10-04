-- Tutor → student announcements (student dashboard popup, tutor/students/view.php, tutor/students/announce.php).
-- The student dashboard reads this table for every student with a tutor, so a missing table means a 500 there.

CREATE TABLE IF NOT EXISTS tutor_announcements (
    id INT NOT NULL AUTO_INCREMENT,
    tutor_id INT NOT NULL,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announce_student (student_id),
    KEY idx_announce_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
