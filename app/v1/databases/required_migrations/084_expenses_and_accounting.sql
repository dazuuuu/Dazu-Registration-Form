-- Expenses and accounting. ADD ONLY: no table or column is dropped or changed, and no money row is rewritten.
--  Branches: thika, nairobi, online (online = students who study online; their fees count for the Online branch).
--  Every *_at DATETIME is server time; dates are calendar dates in East Africa Time. Money is KES.
--  One ADD COLUMN per statement so a re-run skips columns that already exist (MigrationService ignores error 1060).

-- ---------- Which branch an admin runs ----------
CREATE TABLE IF NOT EXISTS staff_branches (
    user_id INT NOT NULL PRIMARY KEY,
    branch ENUM('thika','nairobi') NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The two admins: dazuhubs@gmail.com runs Thika, karanjav494@gmail.com runs Nairobi.
INSERT IGNORE INTO staff_branches (user_id, branch, updated_at)
SELECT id, 'thika', NOW() FROM users WHERE email = 'dazuhubs@gmail.com';
INSERT IGNORE INTO staff_branches (user_id, branch, updated_at)
SELECT id, 'nairobi', NOW() FROM users WHERE email = 'karanjav494@gmail.com';

-- ---------- Purchase requests from a branch admin ----------
-- status: pending (waiting for the superadmin) -> approved (money given, receipt due) -> closed (receipt in)
--         or declined. An admin may have only one request that is pending or approved at a time.
CREATE TABLE IF NOT EXISTS expense_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    branch ENUM('thika','nairobi') NOT NULL,
    items_json TEXT NOT NULL,
    where_buy VARCHAR(200) NOT NULL,
    needed_on DATE NOT NULL,
    estimated_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT NULL,
    status ENUM('pending','approved','declined','closed') NOT NULL DEFAULT 'pending',
    decided_by INT NULL,
    decided_at DATETIME NULL,
    decision_note TEXT NULL,
    amount_given DECIMAL(12,2) NULL,
    receipt_path VARCHAR(255) NULL,
    receipt_name VARCHAR(255) NULL,
    receipt_mime VARCHAR(120) NULL,
    detected_amount DECIMAL(12,2) NULL,
    spent_amount DECIMAL(12,2) NULL,
    spent_source ENUM('detected','manual') NULL,
    receipt_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_er_admin (admin_user_id, status),
    KEY idx_er_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- The superadmin's expense tracker ----------
-- repeat: once | daily | weekly | monthly. A repeating expense counts again every period from start_date until
-- end_date (or for ever when end_date is empty). branch 'general' = the whole institute.
CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'other',
    branch ENUM('thika','nairobi','online','general') NOT NULL DEFAULT 'general',
    amount DECIMAL(12,2) NOT NULL,
    repeat_every ENUM('once','daily','weekly','monthly') NOT NULL DEFAULT 'once',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    note TEXT NULL,
    receipt_path VARCHAR(255) NULL,
    receipt_name VARCHAR(255) NULL,
    receipt_mime VARCHAR(120) NULL,
    request_id INT UNSIGNED NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    stopped_at DATETIME NULL,
    KEY idx_exp_dates (start_date, end_date),
    KEY idx_exp_branch (branch),
    UNIQUE KEY uq_exp_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Tutor shares: corrections that the superadmin approves ----------
-- A student the tutor has finished with stops earning after completed_on's month.
ALTER TABLE tutor_students ADD COLUMN completed_on DATE NULL;

-- type: unassign (stop teaching a student; unpaid money for them is removed), complete (student finished;
--       no more months after that month), adjust (change the balance by an amount, + or -).
CREATE TABLE IF NOT EXISTS tutor_payroll_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    type ENUM('unassign','complete','adjust') NOT NULL,
    student_user_id INT NULL,
    amount DECIMAL(12,2) NULL,
    completed_on DATE NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','declined','cancelled') NOT NULL DEFAULT 'pending',
    decided_by INT NULL,
    decided_at DATETIME NULL,
    decision_note TEXT NULL,
    applied_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_tpr_tutor (tutor_id, status),
    KEY idx_tpr_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Signed changes to what a tutor has earned (negative = less is owed). Counted in the month given.
CREATE TABLE IF NOT EXISTS tutor_payroll_adjustments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    student_user_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    for_month DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    request_id INT UNSIGNED NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_tpa_tutor (tutor_id, for_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail for money actions (who did what, when).
CREATE TABLE IF NOT EXISTS accounting_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    action VARCHAR(40) NOT NULL,
    entity VARCHAR(40) NOT NULL,
    entity_id INT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_al_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
