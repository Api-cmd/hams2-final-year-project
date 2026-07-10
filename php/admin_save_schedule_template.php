<?php
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    send_json(['error' => 'Invalid request.'], 400);
}

$templateId = isset($body['template_id']) ? (int)$body['template_id'] : 0;
$name = clean($body['template_name'] ?? '');
$doctor_id = (int)($body['doctor_id'] ?? 0);
$slot_duration = (int)($body['slot_duration'] ?? 10);
$is_active = isset($body['is_active']) ? (int)$body['is_active'] : 1;
$days = $body['days'] ?? [];
$holidays = $body['holidays'] ?? [];
$exceptions = $body['exceptions'] ?? [];

if (!$name || !$doctor_id || $slot_duration < 5 || $slot_duration > 180) {
    send_json(['error' => 'Template name, doctor and slot duration are required and must be valid.'], 400);
}

// Validate the selected doctor and derive department from the doctor profile.
$stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE doctor_id = ? AND is_active = 1");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doctor) {
    send_json(['error' => 'Selected doctor is not available.'], 400);
}
$dept_id = (int)$doctor['dept_id'];

if (!is_array($days) || count($days) !== 7) {
    send_json(['error' => 'Please provide schedule settings for all seven days of the week.'], 400);
}

$hasWorkingDay = false;
foreach ($days as $day) {
    $dayOfWeek = isset($day['day_of_week']) ? (int)$day['day_of_week'] : -1;
    $isWorking = isset($day['is_working']) ? (int)$day['is_working'] : 0;

    if ($dayOfWeek < 0 || $dayOfWeek > 6) {
        send_json(['error' => 'Invalid day of week provided.'], 400);
    }

    if ($isWorking) {
        $hasWorkingDay = true;
        $start = $day['start_time'] ?? '';
        $end = $day['end_time'] ?? '';

        if (!is_valid_time($start) || !is_valid_time($end)) {
            send_json(['error' => sprintf('Invalid times for %s.', $dayOfWeek)] , 400);
        }

        if (time_to_minutes($end) <= time_to_minutes($start)) {
            send_json(['error' => sprintf('End time must be after start time for %s.', $dayOfWeek)], 400);
        }

        $breakStart = trim($day['break_start'] ?? '');
        $breakEnd = trim($day['break_end'] ?? '');
        if ($breakStart || $breakEnd) {
            if (!is_valid_time($breakStart) || !is_valid_time($breakEnd)) {
                send_json(['error' => sprintf('Invalid break times for %s.', $dayOfWeek)], 400);
            }
            if (time_to_minutes($breakEnd) <= time_to_minutes($breakStart)) {
                send_json(['error' => sprintf('Break end must be after break start for %s.', $dayOfWeek)], 400);
            }
            if (time_to_minutes($breakStart) <= time_to_minutes($start) || time_to_minutes($breakEnd) >= time_to_minutes($end)) {
                send_json(['error' => sprintf('Break must fit inside working hours for %s.', $dayOfWeek)], 400);
            }
        }
    }
}

if (!$hasWorkingDay) {
    send_json(['error' => 'At least one working day must be enabled.'], 400);
}

try {
    $pdo->beginTransaction();

    // Enforce one schedule template per doctor to keep the schedule definition doctor-centric.
    $templateCheckStmt = $pdo->prepare("SELECT template_id FROM schedule_templates WHERE doctor_id = ? AND template_id != ?");
    $templateCheckStmt->execute([$doctor_id, $templateId]);
    if ($templateCheckStmt->fetch()) {
        $pdo->rollBack();
        send_json(['error' => 'A schedule template already exists for this doctor. Edit the existing template or choose a different doctor.'], 400);
    }

    if ($templateId > 0) {
        $stmt = $pdo->prepare("SELECT template_id FROM schedule_templates WHERE template_id = ?");
        $stmt->execute([$templateId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            send_json(['error' => 'Template not found.'], 404);
        }

        $stmt = $pdo->prepare("UPDATE schedule_templates SET template_name = ?, doctor_id = ?, dept_id = ?, slot_duration = ?, is_active = ?, updated_at = NOW() WHERE template_id = ?");
        $stmt->execute([$name, $doctor_id, $dept_id, $slot_duration, $is_active, $templateId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO schedule_templates (template_name, doctor_id, dept_id, slot_duration, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $doctor_id, $dept_id, $slot_duration, $is_active]);
        $templateId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("DELETE FROM template_days WHERE template_id = ?")->execute([$templateId]);
    $pdo->prepare("DELETE FROM template_holidays WHERE template_id = ?")->execute([$templateId]);

    $dayStmt = $pdo->prepare("INSERT INTO template_days (template_id, day_of_week, is_working, start_time, end_time, break_start, break_end) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($days as $day) {
        $dayOfWeek = (int)$day['day_of_week'];
        $isWorking = (int)$day['is_working'];
        $start = $isWorking ? ($day['start_time'] . ':00') : null;
        $end = $isWorking ? ($day['end_time'] . ':00') : null;
        $breakStart = !empty($day['break_start']) ? ($day['break_start'] . ':00') : null;
        $breakEnd = !empty($day['break_end']) ? ($day['break_end'] . ':00') : null;
        $dayStmt->execute([$templateId, $dayOfWeek, $isWorking, $start, $end, $breakStart, $breakEnd]);
    }

    $holidayStmt = $pdo->prepare("INSERT IGNORE INTO template_holidays (template_id, holiday_date, note) VALUES (?, ?, ?)");
    foreach ($holidays as $holiday) {
        $holidayDate = clean($holiday['holiday_date'] ?? '');
        $note = clean($holiday['note'] ?? '');
        if (!$holidayDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
            continue;
        }
        $holidayStmt->execute([$templateId, $holidayDate, $note]);
    }

    // Doctor-level schedule exceptions / leave days
    $pdo->prepare("DELETE FROM schedule_exceptions WHERE doctor_id = ?")->execute([$doctor_id]);
    $exceptionStmt = $pdo->prepare("INSERT IGNORE INTO schedule_exceptions (doctor_id, exception_date, is_working, start_time, end_time, break_start, break_end, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($exceptions as $exception) {
        $exceptionDate = clean($exception['exception_date'] ?? '');
        if (!$exceptionDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exceptionDate)) {
            continue;
        }

        $isWorking = isset($exception['is_working']) ? (int)$exception['is_working'] : 0;
        $startTime = trim($exception['start_time'] ?? '');
        $endTime   = trim($exception['end_time'] ?? '');
        $breakStart = trim($exception['break_start'] ?? '');
        $breakEnd   = trim($exception['break_end'] ?? '');
        $note       = clean($exception['note'] ?? '');

        if ($isWorking) {
            if (!is_valid_time($startTime) || !is_valid_time($endTime)) {
                send_json(['error' => sprintf('Invalid exception times for %s.', $exceptionDate)], 400);
            }
            if (time_to_minutes($endTime) <= time_to_minutes($startTime)) {
                send_json(['error' => sprintf('Exception end must be after start for %s.', $exceptionDate)], 400);
            }
            if (($breakStart && !is_valid_time($breakStart)) || ($breakEnd && !is_valid_time($breakEnd))) {
                send_json(['error' => sprintf('Invalid exception break times for %s.', $exceptionDate)], 400);
            }
            if ($breakStart && $breakEnd) {
                if (time_to_minutes($breakEnd) <= time_to_minutes($breakStart)) {
                    send_json(['error' => sprintf('Exception break end must be after break start for %s.', $exceptionDate)], 400);
                }
                if (time_to_minutes($breakStart) <= time_to_minutes($startTime) || time_to_minutes($breakEnd) >= time_to_minutes($endTime)) {
                    send_json(['error' => sprintf('Exception break must fit inside working hours for %s.', $exceptionDate)], 400);
                }
            }
        } else {
            $startTime = null;
            $endTime = null;
            $breakStart = null;
            $breakEnd = null;
        }

        $exceptionStmt->execute([
            $doctor_id,
            $exceptionDate,
            $isWorking,
            $startTime ? ($startTime . ':00') : null,
            $endTime ? ($endTime . ':00') : null,
            $breakStart ? ($breakStart . ':00') : null,
            $breakEnd ? ($breakEnd . ':00') : null,
            $note,
        ]);
    }

    $pdo->commit();
    send_json(['success' => true, 'template_id' => $templateId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[HAMS] Error saving schedule template: ' . $e->getMessage());
    send_json(['error' => 'Failed to save schedule template', 'detail' => $e->getMessage()], 500);
}
