-- 128_secretary_enquiry_reminder_log.sql
-- Follow-up reminders for the enquiries log (app/v1/tools/secretary_enquiry_reminders.php, run from cron). NEW TABLE ONLY. Safe to run twice.
--  secretary_enquiry_reminder_log: one row for every reminder that is claimed, per enquiry, person, follow-up date and channel (app = in-app notice and push through the existing notification code, email).
--                                  UNIQUE on those four, so a reminder can never go out twice, even if the script runs twice or two runs overlap: a run first claims the row (INSERT) and only the run that got it sends.
--                                  status: claimed, sent, failed or skipped; detail says why.
CREATE TABLE IF NOT EXISTS secretary_enquiry_reminder_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT NOT NULL,
    user_id INT NOT NULL,
    follow_up_date DATE NOT NULL,
    channel VARCHAR(8) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'claimed',
    detail VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_serl_once (enquiry_id, user_id, follow_up_date, channel),
    KEY idx_serl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
