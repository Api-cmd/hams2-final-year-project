-- ===========================================================
-- DROP template_id COLUMN FROM time_slots (SAFE, ONLY IF NOT USING)
-- ===========================================================

USE hams2_db;

-- First, drop the foreign key constraint if it exists
ALTER TABLE time_slots
DROP FOREIGN KEY IF EXISTS fk_time_slots_template_id;

-- Then, drop the column
ALTER TABLE time_slots
DROP COLUMN IF EXISTS template_id;

-- Also drop the index if it exists (though we didn't add one, just to be safe)
ALTER TABLE time_slots
DROP INDEX IF EXISTS idx_template_id;
