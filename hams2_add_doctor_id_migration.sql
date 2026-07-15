-- ===========================================================
-- ADD doctor_id COLUMN TO time_slots TABLE
-- ===========================================================

USE hams2_db;

-- Step 1: Add doctor_id column (nullable, with foreign key)
ALTER TABLE time_slots
ADD COLUMN IF NOT EXISTS doctor_id INT NULL AFTER dept_id;

-- Step 2: Add foreign key constraint to doctors table
ALTER TABLE time_slots
ADD CONSTRAINT IF NOT EXISTS fk_time_slots_doctor_id
FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL;

-- Step 3: Add index for doctor_id + slot_date to optimize queries
ALTER TABLE time_slots
ADD INDEX IF NOT EXISTS idx_doctor_date (doctor_id, slot_date);
