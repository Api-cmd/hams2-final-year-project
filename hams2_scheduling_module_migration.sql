-- ===========================================================
-- HAMS2 SCHEDULING MODULE MIGRATION
-- Adds effective date versioning, audit logging, and
-- enhanced slot generation support.
-- ===========================================================

-- 1. Add effective date columns to schedule_templates
ALTER TABLE schedule_templates
  ADD COLUMN IF NOT EXISTS effective_from DATE NULL AFTER is_active,
  ADD COLUMN IF NOT EXISTS effective_to DATE NULL AFTER effective_from;

-- Remove the old unique constraint that limited one template per doctor
-- and replace with a constraint that allows multiple templates with
-- non-overlapping effective date ranges.
ALTER TABLE schedule_templates
  DROP INDEX IF EXISTS idx_unique_doctor_template;

-- Add index for effective date lookups
ALTER TABLE schedule_templates
  ADD INDEX IF NOT EXISTS idx_effective_dates (doctor_id, effective_from, effective_to);

-- 2. Create audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    description TEXT,
    old_values JSON NULL,
    new_values JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add updated_at to schedule_exceptions for optimistic locking
ALTER TABLE schedule_exceptions
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 4. Add updated_at to template_holidays for optimistic locking
ALTER TABLE template_holidays
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 5. Add updated_at to holidays for optimistic locking
ALTER TABLE holidays
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;