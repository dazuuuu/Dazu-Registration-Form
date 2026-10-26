-- Notification choices: every person decides which kinds of notifications they receive. ADD ONLY.
-- No row = off. Nothing is sent to a person until they switch that kind on (Notifications page → "Choose what you are told about").
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT NOT NULL,
    category VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (user_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
