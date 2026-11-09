-- 101_course_pages.sql
-- Course landing pages: a page of its own for a course at the site root (dazu.ac.ke/<slug>), made in Admin → Courses → Course pages
-- and shared in ads. Not in the navigation; listed in the sitemap. ADD ONLY.
CREATE TABLE IF NOT EXISTS course_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    slug VARCHAR(120) NOT NULL,
    meta_title VARCHAR(70) NOT NULL,
    meta_description VARCHAR(170) NOT NULL,
    keywords VARCHAR(255) NULL,
    hero_heading VARCHAR(150) NOT NULL,
    hero_text VARCHAR(400) NULL,
    video_url VARCHAR(500) NULL,
    sections_json MEDIUMTEXT NOT NULL,
    cards_json MEDIUMTEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cp_course (course_id),
    UNIQUE KEY uq_cp_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
