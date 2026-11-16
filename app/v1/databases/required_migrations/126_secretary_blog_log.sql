-- 126_secretary_blog_log.sql
-- Secretary blogs: the record of every blog action, with the name of the person and the time (the activity record built in a later step reads it). NEW TABLE ONLY. Safe to run twice.
--  secretary_blog_log: one row per action.
--    action        created, updated (changed live), submitted (new post sent for approval), change_submitted, resubmitted, unpublished, republished, approved, sent_back
--    actor_name    the name of the person, as it was at the time (name only); actor_role is 'secretary' or 'admin' (for the record, not for display)
--    blog_id / submission_id / secretary_id   what it was about (any may be empty); title is the post title at the time; detail is a short note (for example the reason a post was sent back)
CREATE TABLE IF NOT EXISTS secretary_blog_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(24) NOT NULL,
    blog_id INT DEFAULT NULL,
    submission_id INT DEFAULT NULL,
    secretary_id INT DEFAULT NULL,
    actor_user_id INT DEFAULT NULL,
    actor_name VARCHAR(160) NOT NULL DEFAULT '',
    actor_role VARCHAR(10) NOT NULL DEFAULT 'secretary',
    title VARCHAR(255) NOT NULL DEFAULT '',
    detail VARCHAR(400) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sbl_blog (blog_id, id),
    KEY idx_sbl_secretary (secretary_id, id),
    KEY idx_sbl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
