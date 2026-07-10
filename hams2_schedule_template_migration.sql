-- Migration script to add schedule template support to an existing HAMS2 database.
-- Run this only after verifying the database backup.

ALTER TABLE time_slots
  ADD COLUMN IF NOT EXISTS template_id INT NULL,
  ADD COLUMN IF NOT EXISTS doctor_id INT NULL,
  ADD COLUMN IF NOT EXISTS capacity INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS booked_count INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE time_slots
  ADD INDEX IF NOT EXISTS idx_template_id (template_id),
  ADD INDEX IF NOT EXISTS idx_doctor_id (doctor_id),
  ADD UNIQUE INDEX IF NOT EXISTS idx_unique_slot (dept_id, doctor_id, slot_date, start_time);

CREATE TABLE IF NOT EXISTS schedule_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    dept_id INT NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    slot_duration INT NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    INDEX idx_doctor_id (doctor_id),
    INDEX idx_dept_id (dept_id),
    INDEX idx_active (is_active),
    UNIQUE INDEX idx_template_name_doctor (doctor_id, template_name),
    UNIQUE INDEX idx_unique_doctor_template (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_days (
    template_day_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    is_working TINYINT(1) NOT NULL DEFAULT 0,
    start_time TIME NULL,
    end_time TIME NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE CASCADE,
    UNIQUE INDEX idx_template_day (template_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    note VARCHAR(150),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE CASCADE,
    UNIQUE INDEX idx_template_holiday (template_id, holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_exceptions (
    exception_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    exception_date DATE NOT NULL,
    is_working TINYINT(1) NOT NULL DEFAULT 0,
    start_time TIME NULL,
    end_time TIME NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    note VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    UNIQUE INDEX idx_doctor_exception (doctor_id, exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
