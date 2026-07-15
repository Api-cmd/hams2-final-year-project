-- ===========================================================
-- HAMS2 OPTIMIZED TIME_SLOTS TABLE SCHEMA
-- Analysis of fields and optimized structure
-- ===========================================================

USE hams2_db;

-- ===========================================================
-- 1. FIELD ANALYSIS
-- ===========================================================
--
-- Current fields in your table:
-- ✓ slot_id       (PRIMARY KEY, auto-increment, keep!)
-- ✓ dept_id       (FOREIGN KEY to departments, keep!)
-- ✓ doctor_id     (NEW field we just added, FOREIGN KEY to doctors, keep!)
-- ✓ slot_date     (DATE of appointment slot, keep!)
-- ✓ start_time    (TIME when slot starts, keep!)
-- ✓ end_time      (TIME when slot ends, keep!)
-- ✓ capacity      (max number of patients per slot, keep!)
-- ✓ booked_count  (number of patients already booked, keep!)
-- ✓ is_booked     (whether slot is fully booked, keep!)
-- ✓ is_active     (whether slot is enabled, keep!)
-- ✓ created_at    (audit field, keep!)
-- ✓ updated_at    (audit field, keep!)
--
-- Fields to DELETE (redundant, not used for core booking):
-- ✗ template_id   (if you're not using schedule templates, remove!)
--
-- ===========================================================
-- 2. OPTIMIZED TABLE CREATION
-- ===========================================================

-- First, drop the old table only if you want a fresh start
-- (WARNING: ONLY RUN THIS IF YOU DON'T NEED EXISTING DATA!)
-- DROP TABLE IF EXISTS time_slots;

-- Create optimized time_slots table
CREATE TABLE IF NOT EXISTS time_slots (
    -- Primary key for unique slot identification
    slot_id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique slot identifier',

    -- Department: required (slot belongs to a department)
    dept_id INT NOT NULL COMMENT 'Department ID this slot belongs to',

    -- Doctor: optional (slot can be unassigned/auto-assign later)
    doctor_id INT NULL COMMENT 'Doctor ID assigned to this slot (NULL = unassigned)',

    -- Date and time for the slot (required for scheduling)
    slot_date DATE NOT NULL COMMENT 'Date of the appointment slot',
    start_time TIME NOT NULL COMMENT 'Start time of the slot',
    end_time TIME NOT NULL COMMENT 'End time of the slot',

    -- Capacity and booking tracking (required for core booking)
    capacity INT NOT NULL DEFAULT 1 COMMENT 'Maximum number of patients per slot',
    booked_count INT NOT NULL DEFAULT 0 COMMENT 'Number of patients already booked in this slot',
    is_booked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag: 0 = available, 1 = fully booked',

    -- Active status (soft delete/enable/disable slot)
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Flag: 0 = disabled, 1 = active/available',

    -- Audit fields for tracking changes
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When slot was created',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When slot was last updated',

    -- Foreign key constraints for referential integrity
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE COMMENT 'Link to departments table',
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL COMMENT 'Link to doctors table (NULL if doctor is deleted)',

    -- Indexes to optimize query performance (critical for booking system)
    INDEX idx_dept_date (dept_id, slot_date) COMMENT 'Speed up queries for slots by department and date',
    INDEX idx_doctor_date (doctor_id, slot_date) COMMENT 'Speed up queries for slots by doctor and date',
    INDEX idx_booked (is_booked) COMMENT 'Speed up queries for available/fully booked slots',
    INDEX idx_active (is_active) COMMENT 'Speed up queries for active slots',
    INDEX idx_date_time (slot_date, start_time) COMMENT 'Speed up queries sorted by date and time',

    -- Unique constraint to prevent duplicate slots
    UNIQUE INDEX idx_unique_slot (dept_id, doctor_id, slot_date, start_time) COMMENT 'Prevent duplicate slots for same dept, doctor, date and start time'

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hospital appointment time slots table (optimized)';
