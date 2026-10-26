-- One signed-in device per account for students, tutors, interns and staff (admins may use several devices).
-- A new sign-in replaces the stored session, which signs the older device out.
CREATE TABLE IF NOT EXISTS user_active_sessions (
    user_id INT NOT NULL PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
