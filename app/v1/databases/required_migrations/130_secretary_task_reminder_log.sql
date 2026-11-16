-- 130_secretary_task_reminder_log.sql
-- Task reminders (app/v1/tools/secretary_task_reminders.php, run from cron). NEW TABLE ONLY. Safe to run twice.
--  secretary_task_reminder_log: one row for every reminder that is claimed, per task, person, kind (day_before or on_day) and channel (app = in-app notice and push through the existing notification code, email).
--                               UNIQUE on those four, so a reminder can never go out twice, even if the script runs twice or two runs overlap: a run first claims the row (INSERT) and only the run that got it sends.
CREATE TABLE IF NOT EXISTS secretary_task_reminder_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    kind VARCHAR(10) NOT NULL,
    channel VARCHAR(8) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'claimed',
    detail VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_strl_once (task_id, user_id, kind, channel),
    KEY idx_strl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
