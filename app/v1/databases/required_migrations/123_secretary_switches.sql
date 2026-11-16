-- 123_secretary_switches.sql
-- Secretary role: the switches (what each secretary may use). NEW TABLE ONLY. Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  secretary_switches: one row per secretary and switch. switch_key is blogs_approval, blogs, staff_calendar, enquiries, tasks, room_visitors or id_card.
--                      switch_value is on or off, and for staff_calendar off, view or edit. blogs_approval "on" means her blog posts wait for the admin to approve them.
--                      A switch with no row counts as off (the application never assumes a switch is on). UNIQUE (secretary_id, switch_key): one value per switch.
CREATE TABLE IF NOT EXISTS secretary_switches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secretary_id INT NOT NULL,
    switch_key VARCHAR(30) NOT NULL,
    switch_value VARCHAR(10) NOT NULL DEFAULT 'off',
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_secretary_switch (secretary_id, switch_key),
    CONSTRAINT fk_secretary_switches_sec FOREIGN KEY (secretary_id) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
