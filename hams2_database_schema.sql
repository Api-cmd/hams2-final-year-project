-- ===========================================================
-- HAMS2 DATABASE SCHEMA
-- Online Appointment Booking System (Patient + Admin only)
-- No doctor login - simplified booking system
-- ===========================================================

-- Create database
CREATE DATABASE IF NOT EXISTS hams2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hams2_db;

-- ===========================================================
-- USERS TABLE
-- Stores patient and admin accounts
-- ===========================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'admin') NOT NULL DEFAULT 'patient',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- DEPARTMENTS TABLE
-- Hospital departments for booking
-- ===========================================================
CREATE TABLE IF NOT EXISTS departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- SCHEDULE TEMPLATES
-- Reusable doctor schedule templates for monthly generation
-- ===========================================================
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

-- ===========================================================
-- TEMPLATE WORKING DAYS
-- Each template defines working days, time range, and optional break.
-- ===========================================================
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

-- ===========================================================
-- TEMPLATE HOLIDAYS
-- Dates a specific doctor should not be scheduled.
-- ===========================================================
CREATE TABLE IF NOT EXISTS template_holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    note VARCHAR(150),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE CASCADE,
    UNIQUE INDEX idx_template_holiday (template_id, holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- GLOBAL HOLIDAYS
-- Hospital-wide holidays that pause schedule generation for all doctors.
-- ===========================================================
CREATE TABLE IF NOT EXISTS holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- SCHEDULE EXCEPTIONS
-- One-off overrides for specific doctor dates without changing the recurring template.
-- ===========================================================
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

-- ===========================================================
-- TIME SLOTS TABLE
-- Available appointment slots by doctor and department.
-- ===========================================================
CREATE TABLE IF NOT EXISTS time_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_id INT NOT NULL,
    doctor_id INT NULL,
    template_id INT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    capacity INT NOT NULL DEFAULT 1,
    booked_count INT NOT NULL DEFAULT 0,
    is_booked TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES schedule_templates(template_id) ON DELETE SET NULL,
    INDEX idx_dept_date (dept_id, slot_date),
    INDEX idx_doctor_date (doctor_id, slot_date),
    INDEX idx_booked (is_booked),
    INDEX idx_active (is_active),
    INDEX idx_date_time (slot_date, start_time),
    UNIQUE INDEX idx_unique_slot (dept_id, doctor_id, slot_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- FAMILY PROFILES TABLE
-- Patients can book appointments for family members
-- ===========================================================
CREATE TABLE IF NOT EXISTS family_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- DOCTORS TABLE
-- Doctor profiles linked to users (doctors cannot log in - admin managed)
-- This table stores doctor-specific information like specialization and bio
-- The actual user account is in the users table with role='staff' (for future use)
-- or we can create user accounts without login access
-- ===========================================================
CREATE TABLE IF NOT EXISTS doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,  -- Can be null if we don't want to create user accounts for doctors
    dept_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    bio TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    INDEX idx_dept (dept_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- APPOINTMENTS TABLE
-- Booked appointments with optional doctor assignment
-- doctor_id is NULL if patient didn't choose a doctor (auto-assigned later)
-- ===========================================================
CREATE TABLE IF NOT EXISTS appointments (
    appt_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_user_id INT NOT NULL,
    family_profile_id INT NULL,
    dept_id INT NOT NULL,
    doctor_id INT NULL,  -- NULL if patient didn't choose, auto-assigned by system
    slot_id INT NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'seen', 'cancelled', 'no_show') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (family_profile_id) REFERENCES family_profiles(profile_id) ON DELETE SET NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id) ON DELETE CASCADE,
    INDEX idx_patient (patient_user_id),
    INDEX idx_dept (dept_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_status (status),
    INDEX idx_date (slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- LOGIN ATTEMPTS TABLE
-- Security monitoring for login attempts
-- ===========================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- INSERT DEFAULT ADMIN USER
-- Email: admin@hams2.com
-- Password: admin123 (change this in production!)
-- ===========================================================
INSERT INTO users (full_name, email, phone, password, role) 
VALUES (
    'System Administrator',
    'admin@hams2.com',
    '+255123456789',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- admin123
    'admin'
) ON DUPLICATE KEY UPDATE email = email;

-- ===========================================================
-- INSERT SAMPLE DEPARTMENTS
-- ===========================================================
INSERT INTO departments (dept_name, description) VALUES
('General Medicine', 'General medical consultations and checkups'),
('Pediatrics', 'Child healthcare and pediatric services'),
('Cardiology', 'Heart and cardiovascular care'),
('Dermatology', 'Skin care and dermatological treatments'),
('Orthopedics', 'Bone and joint care'),
('Neurology', 'Brain and nervous system care')
ON DUPLICATE KEY UPDATE dept_name = dept_name;

-- ===========================================================
-- INSERT SAMPLE DOCTORS
-- Doctors are linked to departments and can be optionally selected by patients
-- If patient doesn't choose a doctor, the system auto-assigns one
-- ===========================================================
INSERT INTO doctors (dept_id, full_name, specialization, bio, is_active) VALUES
-- General Medicine doctors
(1, 'Dr. John Smith', 'General Practitioner', 'Experienced GP with 10+ years in family medicine', 1),
(1, 'Dr. Sarah Johnson', 'Internal Medicine', 'Specialist in internal medicine and chronic disease management', 1),
-- Pediatrics doctors
(2, 'Dr. Emily Chen', 'Pediatrician', 'Board-certified pediatrician specializing in child development', 1),
(2, 'Dr. Michael Brown', 'Pediatric Cardiology', 'Pediatric cardiologist with expertise in congenital heart conditions', 1),
-- Cardiology doctors
(3, 'Dr. Robert Wilson', 'Cardiologist', 'Interventional cardiologist specializing in heart procedures', 1),
(3, 'Dr. Lisa Anderson', 'Cardiac Electrophysiology', 'Specialist in heart rhythm disorders and pacemakers', 1),
-- Dermatology doctors
(4, 'Dr. Amanda White', 'Dermatologist', 'Expert in skin cancer screening and treatment', 1),
(4, 'Dr. David Lee', 'Cosmetic Dermatology', 'Specialist in cosmetic procedures and skin rejuvenation', 1),
-- Orthopedics doctors
(5, 'Dr. James Taylor', 'Orthopedic Surgeon', 'Specialist in joint replacement and sports injuries', 1),
(5, 'Dr. Maria Garcia', 'Spine Surgery', 'Expert in spinal disorders and minimally invasive spine surgery', 1),
-- Neurology doctors
(6, 'Dr. Thomas Martinez', 'Neurologist', 'Specialist in stroke prevention and treatment', 1),
(6, 'Dr. Jennifer Clark', 'Movement Disorders', 'Expert in Parkinson''s disease and other movement disorders', 1)
ON DUPLICATE KEY UPDATE full_name = full_name;

-- ===========================================================
-- SAMPLE SCHEDULE TEMPLATE
-- ===========================================================
INSERT INTO schedule_templates (template_name, dept_id, slot_duration, is_active)
VALUES ('General Medicine Monthly Template', 1, 10, 1)
ON DUPLICATE KEY UPDATE slot_duration = VALUES(slot_duration), is_active = VALUES(is_active);

INSERT INTO template_days (template_id, day_of_week, is_working, start_time, end_time, break_start, break_end)
SELECT t.template_id, sd.day_of_week, sd.is_working, sd.start_time, sd.end_time, sd.break_start, sd.break_end
FROM (
    SELECT 1 AS day_of_week, 1 AS is_working, '08:00:00' AS start_time, '12:00:00' AS end_time, '10:00:00' AS break_start, '10:15:00' AS break_end UNION ALL
    SELECT 2, 1, '08:00:00', '12:00:00', '10:00:00', '10:15:00' UNION ALL
    SELECT 3, 1, '08:00:00', '12:00:00', '10:00:00', '10:15:00' UNION ALL
    SELECT 4, 1, '08:00:00', '12:00:00', '10:00:00', '10:15:00' UNION ALL
    SELECT 5, 1, '08:00:00', '12:00:00', '10:00:00', '10:15:00' UNION ALL
    SELECT 6, 1, '09:00:00', '13:00:00', NULL, NULL UNION ALL
    SELECT 0, 0, NULL, NULL, NULL, NULL
) sd
JOIN schedule_templates t ON t.template_name = 'General Medicine Monthly Template' AND t.dept_id = 1
ON DUPLICATE KEY UPDATE is_working = VALUES(is_working), start_time = VALUES(start_time), end_time = VALUES(end_time), break_start = VALUES(break_start), break_end = VALUES(break_end);

INSERT INTO template_holidays (template_id, holiday_date, note)
SELECT t.template_id, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Next Friday holiday'
FROM schedule_templates t
WHERE t.template_name = 'General Medicine Monthly Template' AND t.dept_id = 1
ON DUPLICATE KEY UPDATE note = VALUES(note);

-- ===========================================================
-- SAMPLE TIME SLOTS (Next 7 days for General Medicine)
-- ===========================================================
INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 1, DATE_ADD(CURDATE(), INTERVAL n DAY), '09:00:00', '09:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 1 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '09:00:00'
);

INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 1, DATE_ADD(CURDATE(), INTERVAL n DAY), '10:00:00', '10:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 1 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '10:00:00'
);

INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 1, DATE_ADD(CURDATE(), INTERVAL n DAY), '11:00:00', '11:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 1 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '11:00:00'
);

INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 1, DATE_ADD(CURDATE(), INTERVAL n DAY), '14:00:00', '14:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 1 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '14:00:00'
);

INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 1, DATE_ADD(CURDATE(), INTERVAL n DAY), '15:00:00', '15:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 1 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '15:00:00'
);

-- ===========================================================
-- SAMPLE TIME SLOTS (Next 7 days for Pediatrics)
-- ===========================================================
INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 2, DATE_ADD(CURDATE(), INTERVAL n DAY), '09:00:00', '09:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 2 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '09:00:00'
);

INSERT INTO time_slots (dept_id, slot_date, start_time, end_time) 
SELECT 2, DATE_ADD(CURDATE(), INTERVAL n DAY), '10:00:00', '10:30:00'
FROM (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) AS numbers
WHERE NOT EXISTS (
    SELECT 1 FROM time_slots 
    WHERE dept_id = 2 
    AND slot_date = DATE_ADD(CURDATE(), INTERVAL n DAY) 
    AND start_time = '10:00:00'
);

-- ===========================================================
-- GRANT PRIVILEGES (adjust username/password as needed)
-- ===========================================================
-- GRANT ALL PRIVILEGES ON hams2_db.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;
