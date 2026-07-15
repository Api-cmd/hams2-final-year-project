-- ===========================================================
-- HAMS2 SCHEDULING MODULE ENHANCEMENT MIGRATION
-- Adds working sessions support, note field to exceptions,
-- and improved slot management.
-- ===========================================================

-- 1. Create template_day_sessions table to replace break_start/break_end
-- This allows multiple working sessions per day (e.g. 08:00-10:00, 10:15-12:30, 13:30-16:30)
CREATE TABLE IF NOT EXISTS template_day_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE CASCADE,
    INDEX idx_template_day (template_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add note column to holidays if not exists
ALTER TABLE holidays
  ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL AFTER name;

-- 3. Add updated_at to time_slots if not exists
ALTER TABLE time_slots
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER is_active;

-- 4. Migrate existing template_days data to sessions
-- For each template day with break, create two sessions
-- For days without break, create one session
INSERT IGNORE INTO template_day_sessions (template_id, day_of_week, start_time, end_time, sort_order)
SELECT 
    template_id, 
    day_of_week,
    start_time,
    CASE 
        WHEN break_start IS NOT NULL AND break_end IS NOT NULL 
             AND break_start > start_time 
        THEN break_start 
        ELSE end_time 
    END as end_time,
    0 as sort_order
FROM template_days 
WHERE is_working = 1 
  AND start_time IS NOT NULL 
  AND end_time IS NOT NULL;

INSERT IGNORE INTO template_day_sessions (template_id, day_of_week, start_time, end_time, sort_order)
SELECT 
    template_id, 
    day_of_week,
    break_end,
    end_time,
    1 as sort_order
FROM template_days 
WHERE is_working = 1 
  AND break_start IS NOT NULL 
  AND break_end IS NOT NULL 
  AND break_end > break_start;

-- 5. Drop old column constraints from template_days (we keep the columns for backward compat)
-- The old break_start/break_end fields will be ignored in new code

-- 6. Add note column to schedule_exceptions if not exists
ALTER TABLE schedule_exceptions
  ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL AFTER break_end;