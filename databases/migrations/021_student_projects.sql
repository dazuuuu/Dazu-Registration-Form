-- databases/migrations/021_student_projects.sql
-- Student project submissions + tutor permission toggle

ALTER TABLE registrations
    ADD COLUMN can_post_project TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id;

ALTER TABLE projects
    ADD COLUMN student_name VARCHAR(150) DEFAULT NULL AFTER created_by,
    ADD COLUMN project_link VARCHAR(500) DEFAULT NULL AFTER student_name,
    ADD COLUMN skillset TEXT DEFAULT NULL AFTER project_link,
    ADD COLUMN year_of_study VARCHAR(50) DEFAULT NULL AFTER skillset,
    ADD COLUMN registration_id INT DEFAULT NULL AFTER year_of_study,
    ADD COLUMN is_student_submission TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_id;

ALTER TABLE projects ADD INDEX idx_projects_student_submission (is_student_submission);
ALTER TABLE projects ADD INDEX idx_projects_registration (registration_id);

INSERT INTO project_categories (category_name, category_slug, category_description)
VALUES ('Student Projects', 'student-projects', 'Projects submitted by students and approved by tutors')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);
