-- Expense categories become admin-manageable instead of a fixed list in code. ADD ONLY.
-- Seeded from the categories that already existed as a PHP constant, so nothing already
-- recorded changes meaning; the superadmin can now add/rename/hide categories from the UI.
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(40) NOT NULL DEFAULT 'fa-receipt',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO expense_categories (code, name, icon, sort_order, created_at) VALUES
('rent', 'Rent', 'fa-house', 1, NOW()),
('electricity', 'Electricity', 'fa-bolt', 2, NOW()),
('water', 'Water', 'fa-faucet-drip', 3, NOW()),
('internet', 'Internet and airtime', 'fa-wifi', 4, NOW()),
('food', 'Food and tea', 'fa-mug-hot', 5, NOW()),
('supplies', 'Office supplies', 'fa-box', 6, NOW()),
('equipment', 'Equipment', 'fa-laptop', 7, NOW()),
('transport', 'Transport', 'fa-car', 8, NOW()),
('marketing', 'Marketing and adverts', 'fa-bullhorn', 9, NOW()),
('repairs', 'Repairs and cleaning', 'fa-broom', 10, NOW()),
('fees', 'Bank and M-Pesa charges', 'fa-money-check-dollar', 11, NOW()),
('purchase', 'Branch purchase', 'fa-cart-shopping', 12, NOW()),
('other', 'Other', 'fa-ellipsis', 13, NOW());
