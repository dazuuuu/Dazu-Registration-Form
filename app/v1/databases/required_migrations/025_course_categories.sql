-- databases/required_migrations/025_course_categories.sql
-- Course categories used by CourseCategoryModel and the admin course category UI.

CREATE TABLE IF NOT EXISTS course_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    icon_path VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course_categories_active_order (is_active, sort_order),
    INDEX idx_course_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE courses
    ADD COLUMN category_id INT NULL AFTER abbreviation;

ALTER TABLE courses
    ADD INDEX idx_courses_category (category_id);

ALTER TABLE courses
    ADD CONSTRAINT fk_courses_category
        FOREIGN KEY (category_id) REFERENCES course_categories(id)
        ON DELETE SET NULL;
