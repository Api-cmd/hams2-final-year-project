-- ===========================================================
-- ANNOUNCEMENTS TABLE
-- Hospital announcements managed by the administrator
-- ===========================================================

CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    posted_date DATE NOT NULL DEFAULT (CURDATE()),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_date (posted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample announcements
INSERT INTO announcements (title, content, posted_date, is_active) VALUES
('Hospital Closed', 'Closed due to maintenance.', CURDATE(), 1),
('Free Diabetes Screening', 'Visit our outpatient department for free diabetes screening.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1)
ON DUPLICATE KEY UPDATE title = title;