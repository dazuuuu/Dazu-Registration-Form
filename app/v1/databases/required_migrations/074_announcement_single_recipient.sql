-- Announcements can go to one person (one student, tutor, intern or admin picked from the system).
ALTER TABLE announcements ADD COLUMN target_user_id INT NULL AFTER course_id;
