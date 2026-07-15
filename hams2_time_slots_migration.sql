-- ===========================================================
-- FIX MISSING COLUMNS IN time_slots TABLE
-- ===========================================================

USE hams2_db;

-- Add capacity column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS capacity INT NOT NULL DEFAULT 1;

-- Add booked_count column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS booked_count INT NOT NULL DEFAULT 0;

-- Add is_booked column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS is_booked TINYINT(1) NOT NULL DEFAULT 0;

-- Add is_active column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Add template_id column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS template_id INT NULL;

-- Add created_at column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at column if it doesn't exist
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add foreign key for template_id if it doesn't exist
ALTER TABLE time_slots
ADD CONSTRAINT IF NOT EXISTS fk_time_slots_template_id
FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE SET NULL;

-- Add indexes if they don't exist (just to be safe)
ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_dept_date (dept_id, slot_date);

ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_doctor_date (doctor_id, slot_date);

ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_booked (is_booked);

ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_active (is_active);

ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_date_time (slot_date, start_time);

-- Add unique index if it doesn't exist
ALTER TABLE time_slots
ADD UNIQUE INDEX IF NOT EXISTS idx_unique_slot (dept_id, doctor_id, slot_date, start_time);
