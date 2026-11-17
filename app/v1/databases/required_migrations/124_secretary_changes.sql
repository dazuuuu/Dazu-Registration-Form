-- 124_secretary_changes.sql
-- Secretaries list: who changed what, and when. NEW TABLE ONLY. Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  secretary_changes: one row for every change the admin makes to a secretary after she was added: a switch (blogs_approval, blogs, staff_calendar, enquiries, tasks, room_visitors, id_card),
--                     her status (field_key "status": active, on_leave, left), a suspension ("suspended": on or off) or a password reset ("password": reset). old_value / new_value are the values
--                     before and after; changed_by is the users.id of the admin who did it and changed_by_name is that admin's name as it was at the time (name only); changed_at is when. A secretary is never deleted, so this history stays with her.
CREATE TABLE IF NOT EXISTS secretary_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secretary_id INT NOT NULL,
    field_key VARCHAR(30) NOT NULL,
    old_value VARCHAR(60) DEFAULT NULL,
    new_value VARCHAR(60) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_by_name VARCHAR(160) DEFAULT NULL,
    changed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_secretary_changes_sec (secretary_id, id),
    CONSTRAINT fk_secretary_changes_sec FOREIGN KEY (secretary_id) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
