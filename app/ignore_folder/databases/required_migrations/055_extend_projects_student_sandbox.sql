-- Extends `projects` so a project can mention a student, link to a live/sandbox
-- demo, and list the languages used, progress and complexity — the same
-- treatment blogs get (title/content/date), but for showcasing student work.
ALTER TABLE projects
    ADD COLUMN student_id INT NULL AFTER category_id,
    ADD COLUMN live_url VARCHAR(500) NULL AFTER cover_image,
    ADD COLUMN languages_used VARCHAR(255) NULL AFTER live_url,
    ADD COLUMN progress TINYINT UNSIGNED NULL DEFAULT 0 AFTER languages_used,
    ADD COLUMN complexity ENUM('beginner','intermediate','advanced') NULL DEFAULT 'beginner' AFTER progress,
    ADD COLUMN project_date DATE NULL AFTER complexity,
    ADD CONSTRAINT fk_projects_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_student (student_id);
