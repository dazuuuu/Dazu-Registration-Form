-- 129_secretary_tasks.sql
-- Tasks the admin gives to a secretary. NEW TABLES ONLY. Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  secretary_tasks:       one row per task. secretary_id is the one secretary who must do it (nobody else sees it). due_at is Nairobi time. status: open or done. done_at is when she ticked it.
--                         given_by / given_by_name: the admin who gave it (name only). calendar_event_id is the private Staff Calendar entry that shows the task on HER calendar (empty when there is none).
--                         Only the normal admin creates, changes and deletes tasks; the secretary only ticks a task and adds notes.
--  secretary_task_notes:  the short notes a secretary adds to a task, with her name and the time.
CREATE TABLE IF NOT EXISTS secretary_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secretary_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_at DATETIME NOT NULL,
    status VARCHAR(6) NOT NULL DEFAULT 'open',
    done_at DATETIME NULL,
    given_by INT NULL,
    given_by_name VARCHAR(160) NOT NULL DEFAULT '',
    calendar_event_id INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_st_secretary (secretary_id, status),
    KEY idx_st_due (due_at),
    CONSTRAINT fk_st_secretary FOREIGN KEY (secretary_id) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS secretary_task_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    secretary_id INT NOT NULL,
    author_name VARCHAR(160) NOT NULL DEFAULT '',
    body VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stn_task (task_id, id),
    CONSTRAINT fk_stn_task FOREIGN KEY (task_id) REFERENCES secretary_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
