-- Log composed and emailed SmS documents for admin history.

CREATE TABLE IF NOT EXISTS sms_composed_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_slug VARCHAR(50) NOT NULL,
    template_label VARCHAR(100) NOT NULL,
    delivery ENUM('download', 'email') NOT NULL,
    recipient_name VARCHAR(150) DEFAULT NULL,
    recipient_email VARCHAR(150) DEFAULT NULL,
    registration_no VARCHAR(50) DEFAULT NULL,
    course_id INT DEFAULT NULL,
    course_title VARCHAR(200) DEFAULT NULL,
    sms_student_id INT UNSIGNED DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    email_subject VARCHAR(255) DEFAULT NULL,
    actor_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_docs_created (created_at),
    INDEX idx_sms_docs_template (template_slug),
    INDEX idx_sms_docs_delivery (delivery),
    INDEX idx_sms_docs_reg_no (registration_no),
    INDEX idx_sms_docs_student (sms_student_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (sms_student_id) REFERENCES sms_students(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
