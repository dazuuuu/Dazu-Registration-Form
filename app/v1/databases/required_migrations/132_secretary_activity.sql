-- 132_secretary_activity.sql
-- The activity record: what each secretary did, with her name and the time (the admin's "Activity" page reads it). NEW TABLE ONLY. Safe to run twice.
--  secretary_activity: one row per thing a secretary did. area: blogs, enquiries, tasks or diary. action: a short word (posted, edited, unpublished, added, status, ticked, cancelled, ...). subject: what it was about
--                      (a blog title, a person's name, a task title...). actor_name: her name as it was at the time (name only). secretary_id lets the page filter by secretary.
--                      dedupe_key is UNIQUE: every part of the system writes through ONE function (secretary_activity() in app/v1/helpers/secretary_activity.php) with a key built from where the event
--                      came from (for example blog:12), so the same event can never be recorded twice.
-- The two statements at the end copy what the blog and enquiry logs already hold (migrations 126 and 127), once: a row is only copied when its dedupe_key is not there yet.
CREATE TABLE IF NOT EXISTS secretary_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secretary_id INT NULL,
    actor_name VARCHAR(160) NOT NULL DEFAULT '',
    area VARCHAR(10) NOT NULL,
    action VARCHAR(24) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    detail VARCHAR(400) NULL,
    dedupe_key VARCHAR(60) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sa_dedupe (dedupe_key),
    KEY idx_sa_secretary (secretary_id, id),
    KEY idx_sa_area (area, id),
    KEY idx_sa_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO secretary_activity (secretary_id, actor_name, area, action, subject, detail, dedupe_key, created_at)
SELECT l.secretary_id, l.actor_name, 'blogs',
       CASE l.action WHEN 'created' THEN 'posted' WHEN 'updated' THEN 'edited' WHEN 'submitted' THEN 'sent for approval' WHEN 'change_submitted' THEN 'sent a change' WHEN 'resubmitted' THEN 'sent again' ELSE l.action END,
       l.title, l.detail, CONCAT('blog:', l.id), l.created_at
FROM secretary_blog_log l
WHERE l.actor_role = 'secretary' AND NOT EXISTS (SELECT 1 FROM secretary_activity a WHERE a.dedupe_key = CONCAT('blog:', l.id));

INSERT INTO secretary_activity (secretary_id, actor_name, area, action, subject, detail, dedupe_key, created_at)
SELECT h.secretary_id, h.actor_name, 'enquiries',
       CASE h.action WHEN 'created' THEN 'added' WHEN 'follow_up' THEN 'follow-up date' WHEN 'taken_over' THEN 'took over' ELSE h.action END,
       e.name, h.new_value, CONCAT('enq:', h.id), h.created_at
FROM secretary_enquiry_history h JOIN secretary_enquiries e ON e.id = h.enquiry_id
WHERE NOT EXISTS (SELECT 1 FROM secretary_activity a WHERE a.dedupe_key = CONCAT('enq:', h.id));
