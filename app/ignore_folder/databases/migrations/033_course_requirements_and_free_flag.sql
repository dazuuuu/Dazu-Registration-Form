-- Course requirements list + free-course flag.
-- requirements: newline-separated list, parsed by dazu_course_requirements() (app/v1/helpers/dazu_theme.php).
--               NULL/empty falls back to a hardcoded default ("A laptop", "Stable internet").
-- is_free:      when set, course_fee is forced to 0 by CourseModel and enrollment skips
--               payment + admin review (CourseRegistrationHandler auto-approves on submit).
USE registration_system;

ALTER TABLE courses
    ADD COLUMN requirements TEXT DEFAULT NULL AFTER description,
    ADD COLUMN is_free TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER course_fee;
