-- Active: 1785849373366@@127.0.0.1@3306@registration_system
-- Free-course scheduling window + a site-wide switch to hide the "Free Courses" nav link.
-- start_date/end_date: optional, shown on the free-course card/details. A course whose
--                       end_date has passed is auto-excluded from CourseModel::getFree()
--                       (see app/v1/models/CourseModel.php) — no cron/cleanup job needed,
--                       it's just left out of the query going forward.
-- free_courses_enabled: site_settings row read by dazu_navbar.php. '0' removes the
--                        "FREE COURSES" nav link entirely; the listing page itself also
--                        shows a "currently closed" message while off.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN start_date DATE DEFAULT NULL AFTER is_free,
    ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('free_courses_enabled', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;   -- no-op if row exists
