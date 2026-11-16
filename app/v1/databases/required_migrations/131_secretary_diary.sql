-- 131_secretary_diary.sql
-- The shared Room and Visitors diary of the secretary team. NEW TABLES ONLY. Safe to run twice.
--  secretary_diary_entries: one row per room booking (kind room) or visitor (kind visitor). Rooms and visitors are plain text; nothing links to a student.
--     room booking: room, branch (nairobi or thika), entry_date, start_time and end_time (Nairobi time), purpose, booked_by (the name the booking is for), created_by (the secretary who added it).
--     visitor:      person_name, sees_who (who they come to see), entry_date, start_time, purpose.
--     status: booked or expected (not yet), arrived and left (visitors, on the day), cancelled (a secretary cancels her own entry; entries are never deleted). The cancel is recorded with the name and the time.
--  secretary_diary_locks:   one row per room and day, only used to take a short lock so two secretaries booking the same room at the same moment cannot both succeed (the server checks the clash inside that lock).
-- Staff Calendar events that have a room in their location are NOT copied here: the diary reads them from the calendar, read only.
CREATE TABLE IF NOT EXISTS secretary_diary_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(8) NOT NULL,
    room VARCHAR(80) NULL,
    branch VARCHAR(10) NULL,
    person_name VARCHAR(120) NULL,
    sees_who VARCHAR(120) NULL,
    entry_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    purpose VARCHAR(300) NOT NULL DEFAULT '',
    booked_by VARCHAR(120) NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'booked',
    arrived_at DATETIME NULL,
    left_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancelled_by_name VARCHAR(160) NULL,
    created_by INT NOT NULL,
    created_by_name VARCHAR(160) NOT NULL DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sde_date (entry_date, start_time),
    KEY idx_sde_room (room, entry_date),
    KEY idx_sde_creator (created_by),
    CONSTRAINT fk_sde_creator FOREIGN KEY (created_by) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS secretary_diary_locks (
    lock_key VARCHAR(120) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
