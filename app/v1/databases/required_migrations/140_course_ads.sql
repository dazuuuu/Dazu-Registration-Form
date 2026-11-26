-- Admin-managed promotional ads for a course (a Black-Friday-style image popup shown on the public
-- site). Each ad advertises exactly one course; its button deep-links straight to that course's page
-- and inline Book Now form. Only one ad is shown at a time (the newest active one).
CREATE TABLE IF NOT EXISTS course_ads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(150) NULL,
    subtitle VARCHAR(255) NULL,
    button_text VARCHAR(40) NOT NULL DEFAULT 'Apply now',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_ca_active (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
