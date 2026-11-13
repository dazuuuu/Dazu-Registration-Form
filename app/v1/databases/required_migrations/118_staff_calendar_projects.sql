-- 118_staff_calendar_projects.sql
-- Staff Calendar: projects (multi-day events with tasks, people and a progress figure). NEW COLUMNS on the checklist table made by migration 113 and ONE NEW TABLE. Safe to run twice
-- (a column or key that already exists is skipped by the updater, the table is CREATE TABLE IF NOT EXISTS, the UPDATE only touches rows that still need it).
-- A normal event's checklist keeps working as before (a simple tick, no person, no dates). For a project the same rows are its tasks:
--  assignee_user_id   the tutor or super admin the task is given to (empty = nobody yet)
--  start_date         optional start of the task
--  due_date           when the task is due
--  status             todo, in_progress or done (ticking the box sets done; is_done stays in step: 1 only when the status is done)
-- staff_calendar_task_notices: a PENDING notification row when a task is given to someone (kind assigned) and another for the day before it is due (kind due_before, send_on = that day), only while
--  the task is not done. Nothing is sent from here: sending comes with the reminders. sent_at stays empty until something sends it. The existing notification tables are not used.
ALTER TABLE staff_calendar_checklist ADD COLUMN assignee_user_id INT NULL;
ALTER TABLE staff_calendar_checklist ADD COLUMN start_date DATE NULL;
ALTER TABLE staff_calendar_checklist ADD COLUMN due_date DATE NULL;
ALTER TABLE staff_calendar_checklist ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'todo';
ALTER TABLE staff_calendar_checklist ADD KEY idx_scc_assignee (assignee_user_id);
UPDATE staff_calendar_checklist SET status = 'done' WHERE is_done = 1 AND status = 'todo';

CREATE TABLE IF NOT EXISTS staff_calendar_task_notices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    to_user_id INT NOT NULL,
    kind VARCHAR(12) NOT NULL,
    send_on DATE NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sctn_task (task_id, sent_at),
    KEY idx_sctn_event (event_id),
    KEY idx_sctn_to (to_user_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
