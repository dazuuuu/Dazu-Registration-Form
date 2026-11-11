-- 102_course_page_cover.sql
-- Course landing pages: a picture of their own (replaces the course cover on the page and in shared links). ADD ONLY.
ALTER TABLE course_pages ADD COLUMN cover_image VARCHAR(255) NULL AFTER video_url;
