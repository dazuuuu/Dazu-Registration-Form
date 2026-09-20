-- Free-course applicants explain why they should be selected instead of submitting
-- payment proof (see courses.is_free, migration 033). NULL for paid registrations.
USE registration_system;

ALTER TABLE registrations
    ADD COLUMN free_course_reason TEXT DEFAULT NULL AFTER payment_reference;
