-- Course categories for homepage "Popular Courses" tabs
CREATE TABLE IF NOT EXISTS course_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    icon_path VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_sort (is_active, sort_order)
);

ALTER TABLE courses
    ADD COLUMN category_id INT NULL DEFAULT NULL AFTER abbreviation,
    ADD INDEX idx_course_category (category_id);

-- FK added separately so migration is safe if column already exists
-- ALTER TABLE courses ADD CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL;
