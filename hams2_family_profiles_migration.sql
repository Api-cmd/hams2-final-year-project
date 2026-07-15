-- ===========================================================
-- FIX FAMILY PROFILES TABLE
-- Changes:
--  1. Rename `user_id` → `patient_user_id` (matches the PHP code)
--  2. Add `date_of_birth` column (missing from original schema!)
--  3. Drop old foreign key constraint and add new one
-- ===========================================================
USE hams2_db;

-- Step 1: Check if family_profiles exists
-- Step 2: Add date_of_birth column if it doesn't exist
ALTER TABLE family_profiles 
ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER relationship;

-- Step 3: Add phone column if missing (just in case, but not used right now)
ALTER TABLE family_profiles 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER date_of_birth;

-- Step 4: Rename user_id → patient_user_id, fixing the foreign key
-- First, check if the column is already named correctly
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'hams2_db' 
      AND TABLE_NAME = 'family_profiles' 
      AND COLUMN_NAME = 'patient_user_id'
);

SET @old_col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'hams2_db' 
      AND TABLE_NAME = 'family_profiles' 
      AND COLUMN_NAME = 'user_id'
);

-- If user_id exists and patient_user_id does NOT: rename and fix FK
SET @sql = IF(
    @old_col_exists = 1 AND @col_exists = 0,
    CONCAT(
        'ALTER TABLE family_profiles ',
        'DROP FOREIGN KEY IF EXISTS family_profiles_ibfk_1; ',
        'ALTER TABLE family_profiles ',
        'CHANGE COLUMN user_id patient_user_id INT NOT NULL; ',
        'ALTER TABLE family_profiles ',
        'ADD CONSTRAINT family_profiles_patient_fk ',
        'FOREIGN KEY (patient_user_id) REFERENCES users(user_id) ON DELETE CASCADE; ',
        'ALTER TABLE family_profiles ',
        'ADD INDEX IF NOT EXISTS idx_patient_user (patient_user_id);'
    ),
    'SELECT "No rename needed: patient_user_id already exists" AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Update the original schema file to reflect the correct table structure
-- (we'll modify the sql file too!)
