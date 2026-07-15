-- Migration: add cancellation_reason to appointments
-- Run once on existing databases: mysql -u root hams2_db < hams2_appointments_migration.sql

USE hams2_db;

-- Safe to ignore "Duplicate column" if already applied
ALTER TABLE appointments
    ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER notes;
