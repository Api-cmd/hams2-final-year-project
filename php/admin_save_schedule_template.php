<?php
// ===========================================================
// php/admin_save_schedule_template.php
// Saves/updates a schedule template with working sessions.
// When updating, detects future slots and offers conflict review.
// ===========================================================
require_once 'config.php';
require_once 'audit_log.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    send_json(['error' => 'Invalid request.'], 400);
}

$templateId = isset($body['template_id']) ? (int)$body['template_id'] : 0;
$name = clean($body['template_name'] ?? '');
$doctor_id = (int)($body['doctor_id'] ?? 0);
$dept_id_from_frontend = isset($body['dept_id']) ? (int)$body['dept_id'] : 0;
$slot_duration = (int)($body['slot_duration'] ?? 10);
$is_active = isset($body['is_active']) ? (int)$body['is_active'] : 1;
$effective_from = clean($body['effective_from'] ?? '');
$effective_to = clean($body['effective_to'] ?? '');
$days = $body['days'] ?? [];
$sessions = $body['sessions'] ?? [];
$holidays = $body['holidays'] ?? [];
$exceptions = $body['exceptions'] ?? [];
$force = isset($body['force']) ? (bool)$body['force'] : false;

if (!$name || !$doctor_id || $slot_duration < 5 || $slot_duration > 180) {
    send_json(['error' => 'Template name, doctor and slot duration are required and must be valid.'], 400);
}

// Validate effective dates
if ($effective_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_from)) {
    send_json(['error' => 'Effective from date must use YYYY-MM-DD format.'], 400);
}
if ($effective_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_to)) {
    send_json(['error' => 'Effective to date must use YYYY-MM-DD format.'], 400);
}
if ($effective_from && $effective_to && $effective_to <= $effective_from) {
    send_json(['error' => 'Effective to date must be after effective from date.'], 400);
}

// Validate the selected doctor and derive department
$stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE doctor_id = ? AND is_active = 1");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doctor) {
    send_json(['error' => 'Selected doctor is not available.'], 400);
}
$dept_id = $dept_id_from_frontend > 0 ? $dept_id_from_frontend : (int)$doctor['dept_id'];

// Helper to strip seconds from HH:MM:SS to HH:MM
function strip_secs($t) {
    if (!is_string($t) || $t === '') return $t;
    $parts = explode(':', $t);
    return count($parts) >= 2 ? $parts[0] . ':' . $parts[1] : $t;
}

// Validate days (legacy format with break fields - still accepted)
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
        // Strip seconds to handle HH:MM:SS format from DB
        $start = strip_secs($day['start_time'] ?? '');
        $end = strip_secs($day['end_time'] ?? '');

        if (!is_valid_time($start) || !is_valid_time($end)) {
            send_json(['error' => sprintf('Invalid times for %s.', $dayOfWeek)], 400);
        }

        if (time_to_minutes($end) <= time_to_minutes($start)) {
            send_json(['error' => sprintf('End time must be after start time for %s.', $dayOfWeek)], 400);
        }

        $breakStart = strip_secs(trim($day['break_start'] ?? ''));
        $breakEnd = strip_secs(trim($day['break_end'] ?? ''));
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

    // Check for overlapping effective date ranges with other templates for the same doctor
    if ($effective_from) {
        $overlapCheck = $pdo->prepare("
            SELECT template_id, template_name, effective_from, effective_to
            FROM schedule_templates
            WHERE doctor_id = ?
              AND template_id != ?
              AND is_active = 1
              AND (
                (effective_from IS NULL AND effective_to IS NULL) OR
                (effective_from IS NULL AND (effective_to IS NULL OR effective_to >= ?)) OR
                (effective_to IS NULL AND (effective_from IS NULL OR effective_from <= ?)) OR
                (effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?))
              )
        ");
        $overlapCheck->execute([$doctor_id, $templateId, $effective_from, $effective_from, $effective_to ?: $effective_from, $effective_from]);
        $overlapping = $overlapCheck->fetchAll();

        if (!empty($overlapping)) {
            $pdo->rollBack();
            $overlapNames = array_map(function($t) {
                return $t['template_name'] . ' (' . ($t['effective_from'] ?? 'no start') . ' - ' . ($t['effective_to'] ?? 'no end') . ')';
            }, $overlapping);
            send_json([
                'error' => 'This template overlaps with existing active template(s): ' . implode(', ', $overlapNames) . '. Adjust the effective dates or deactivate the conflicting template first.',
                'overlapping_templates' => $overlapping,
            ], 400);
        }
    }

    // Load old values for audit logging
    $oldValues = null;
    if ($templateId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM schedule_templates WHERE template_id = ?");
        $stmt->execute([$templateId]);
        $oldTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($oldTemplate) {
            $oldValues = $oldTemplate;
        } else {
            $pdo->rollBack();
            send_json(['error' => 'Template not found.'], 404);
        }
    }

    // --- CONFLICT DETECTION for safe template updates ---
    if ($templateId > 0 && !$force) {
        $futureSlotsStmt = $pdo->prepare("
            SELECT COUNT(*) AS slot_count,
                   SUM(is_booked) AS booked_count
            FROM time_slots
            WHERE template_id = ?
              AND slot_date >= CURDATE()
        ");
        $futureSlotsStmt->execute([$templateId]);
        $futureSlots = $futureSlotsStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$futureSlots['slot_count'] > 0) {
            $pdo->rollBack();
            send_json([
                'error' => 'This template has ' . $futureSlots['slot_count'] . ' future slot(s) with ' . $futureSlots['booked_count'] . ' booking(s). Changes may affect existing appointments.',
                'need_confirmation' => true,
                'future_slots' => (int)$futureSlots['slot_count'],
                'booked_slots' => (int)$futureSlots['booked_count'],
            ], 409);
        }
    }

    // Save or update the template
    if ($templateId > 0) {
        $stmt = $pdo->prepare("
            UPDATE schedule_templates
            SET template_name = ?, doctor_id = ?, dept_id = ?, slot_duration = ?,
                is_active = ?, effective_from = ?, effective_to = ?, updated_at = NOW()
            WHERE template_id = ?
        ");
        $stmt->execute([$name, $doctor_id, $dept_id, $slot_duration, $is_active, $effective_from ?: null, $effective_to ?: null, $templateId]);
        $action = 'template_updated';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO schedule_templates (template_name, doctor_id, dept_id, slot_duration, is_active, effective_from, effective_to)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $doctor_id, $dept_id, $slot_duration, $is_active, $effective_from ?: null, $effective_to ?: null]);
        $templateId = (int)$pdo->lastInsertId();
        $action = 'template_created';
    }

    // ==========================================================
    // SAVE WORKING DAYS (legacy format with break fields)
    // ==========================================================
    $pdo->prepare("DELETE FROM template_days WHERE template_id = ?")->execute([$templateId]);
    $dayStmt = $pdo->prepare("INSERT INTO template_days (template_id, day_of_week, is_working, start_time, end_time, break_start, break_end) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($days as $day) {
        $dayOfWeek = (int)$day['day_of_week'];
        $isWorking = (int)$day['is_working'];
        $start = $isWorking ? (strip_secs($day['start_time']) . ':00') : null;
        $end = $isWorking ? (strip_secs($day['end_time']) . ':00') : null;
        $breakStart = !empty($day['break_start']) ? (strip_secs($day['break_start']) . ':00') : null;
        $breakEnd = !empty($day['break_end']) ? (strip_secs($day['break_end']) . ':00') : null;
        $dayStmt->execute([$templateId, $dayOfWeek, $isWorking, $start, $end, $breakStart, $breakEnd]);
    }

    // ==========================================================
    // SAVE WORKING SESSIONS (new approach - replaces break fields)
    // ==========================================================
    $pdo->prepare("DELETE FROM template_day_sessions WHERE template_id = ?")->execute([$templateId]);
    if (!empty($sessions)) {
        $sessionStmt = $pdo->prepare("INSERT INTO template_day_sessions (template_id, day_of_week, start_time, end_time, sort_order, session_name) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($sessions as $session) {
            $dayOfWeek = (int)($session['day_of_week'] ?? 0);
            $startTime = strip_secs($session['start_time'] ?? '');
            $endTime = strip_secs($session['end_time'] ?? '');
            $sortOrder = (int)($session['sort_order'] ?? 0);
            $sessionName = clean($session['session_name'] ?? '');
            if ($startTime && $endTime) {
                $sessionStmt->execute([$templateId, $dayOfWeek, $startTime . ':00', $endTime . ':00', $sortOrder, $sessionName ?: null]);
            }
        }
    } else {
        // Auto-generate sessions from legacy days data
        $sessionStmt = $pdo->prepare("INSERT INTO template_day_sessions (template_id, day_of_week, start_time, end_time, sort_order, session_name) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($days as $day) {
            $dayOfWeek = (int)$day['day_of_week'];
            $isWorking = (int)$day['is_working'];
            if (!$isWorking) continue;
            $start = strip_secs($day['start_time'] ?? '');
            $end = strip_secs($day['end_time'] ?? '');
            $breakStart = strip_secs(trim($day['break_start'] ?? ''));
            $breakEnd = strip_secs(trim($day['break_end'] ?? ''));
            if ($breakStart && $breakEnd && $breakEnd > $breakStart) {
                if ($breakStart > $start) {
                    $sessionStmt->execute([$templateId, $dayOfWeek, $start . ':00', $breakStart . ':00', 0, 'Morning Session']);
                }
                if ($breakEnd < $end) {
                    $sessionStmt->execute([$templateId, $dayOfWeek, $breakEnd . ':00', $end . ':00', 1, 'Afternoon Session']);
                }
            } else {
                $sessionStmt->execute([$templateId, $dayOfWeek, $start . ':00', $end . ':00', 0, 'Morning Session']);
            }
        }
    }

    // Replace template holidays
    $pdo->prepare("DELETE FROM template_holidays WHERE template_id = ?")->execute([$templateId]);
    $holidayStmt = $pdo->prepare("INSERT IGNORE INTO template_holidays (template_id, holiday_date, note) VALUES (?, ?, ?)");
    foreach ($holidays as $holiday) {
        $holidayDate = clean($holiday['holiday_date'] ?? '');
        $note = clean($holiday['note'] ?? '');
        if (!$holidayDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
            continue;
        }
        $holidayStmt->execute([$templateId, $holidayDate, $note]);
    }

    // Replace doctor exceptions
    $pdo->prepare("DELETE FROM schedule_exceptions WHERE doctor_id = ?")->execute([$doctor_id]);
    $exceptionStmt = $pdo->prepare("INSERT IGNORE INTO schedule_exceptions (doctor_id, exception_date, is_working, start_time, end_time, break_start, break_end, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($exceptions as $exception) {
        $exceptionDate = clean($exception['exception_date'] ?? '');
        if (!$exceptionDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exceptionDate)) {
            continue;
        }

        $isWorking = isset($exception['is_working']) ? (int)$exception['is_working'] : 0;
        $overrideType = clean($exception['override_type'] ?? '');
        
        // Handle block_time type - store blocked range in note as JSON
        if ($overrideType === 'block_time') {
            $blockStart = strip_secs(trim($exception['block_start'] ?? ''));
            $blockEnd = strip_secs(trim($exception['block_end'] ?? ''));
            $noteData = json_encode([
                'type' => 'block_time',
                'block_start' => $blockStart,
                'block_end' => $blockEnd,
                'reason' => clean($exception['reason'] ?? '')
            ]);
            $note = $noteData;
            // For block_time, doctor is still working but with blocked hours
            // We'll handle the blocking during slot generation
            $isWorking = 1;
            $startTime = null;
            $endTime = null;
            $breakStart = null;
            $breakEnd = null;
        } else {
            // Extract times from sessions array if available (custom schedule)
            if (isset($exception['sessions']) && is_array($exception['sessions']) && !empty($exception['sessions'])) {
                $firstSession = $exception['sessions'][0];
                $startTime = strip_secs(trim($firstSession['start_time'] ?? ''));
                $endTime   = strip_secs(trim($firstSession['end_time'] ?? ''));
                $breakStart = null;
                $breakEnd = null;
            } else {
                // Legacy format: direct start_time/end_time fields
                $startTime = strip_secs(trim($exception['start_time'] ?? ''));
                $endTime   = strip_secs(trim($exception['end_time'] ?? ''));
                $breakStart = strip_secs(trim($exception['break_start'] ?? ''));
                $breakEnd   = strip_secs(trim($exception['break_end'] ?? ''));
            }
            
            $note = clean($exception['note'] ?? $exception['reason'] ?? '');

            // If times are empty, treat as full day off regardless of is_working flag
            if (empty($startTime) || empty($endTime)) {
                $isWorking = 0;
                $startTime = null;
                $endTime = null;
                $breakStart = null;
                $breakEnd = null;
            }
        }

        // Skip time validation for block_time - times are stored in note as JSON
        if ($isWorking && $overrideType !== 'block_time') {
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

    // ==========================================================
    // REGENERATE FUTURE UNBOOKED SLOTS (if template was updated)
    // ==========================================================
    if ($action === 'template_updated' && $force) {
        $pdo->prepare("
            DELETE FROM time_slots 
            WHERE template_id = ? 
              AND slot_date >= CURDATE() 
              AND is_booked = 0
        ")->execute([$templateId]);
    }

    $pdo->commit();

    // Build new values for audit log
    $newValues = [
        'template_name' => $name,
        'doctor_id' => $doctor_id,
        'dept_id' => $dept_id,
        'slot_duration' => $slot_duration,
        'is_active' => $is_active,
        'effective_from' => $effective_from,
        'effective_to' => $effective_to,
        'days_count' => count($days),
        'sessions_count' => count($sessions),
        'holidays_count' => count($holidays),
        'exceptions_count' => count($exceptions),
    ];

    audit_log(
        $pdo,
        (int)$_SESSION['user_id'],
        $action,
        'schedule_template',
        $templateId,
        ($action === 'template_created' ? 'Created' : 'Updated') . " template: $name for doctor ID $doctor_id",
        $oldValues,
        $newValues
    );

    send_json(['success' => true, 'template_id' => $templateId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[HAMS] Error saving schedule template: ' . $e->getMessage());
    send_json(['error' => 'Failed to save schedule template', 'detail' => $e->getMessage()], 500);
}