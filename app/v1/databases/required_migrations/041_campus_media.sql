CREATE TABLE IF NOT EXISTS campus_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    file_path VARCHAR(255) NOT NULL,
    title VARCHAR(160) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campus_media_status_sort (status, sort_order, created_at),
    INDEX idx_campus_media_type (media_type),
    CONSTRAINT fk_campus_media_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);
