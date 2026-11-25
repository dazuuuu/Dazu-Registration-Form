-- A tutor's own reusable "subject library": create a subject/unit once (e.g. "Networking Basics"),
-- then push it into any number of the tutor's own courses as a normal course_subjects row (tutor_id = <tutor>).
-- This table only stores the reusable template; course_subjects is unchanged and remains the only
-- place a subject actually lives once it is in use on an assignment.
CREATE TABLE IF NOT EXISTS tutor_subject_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(20) NULL,
    weight DECIMAL(6,2) NULL,
    monthly_expected TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_tst_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
