<?php
// ===========================================================
// php/admin_generate_schedule_slots.php
// Generates time slots from a schedule template using working sessions.
// Supports: 1, 3, 6, 12 month ranges.
// Handles holidays, doctor exceptions, and existing slot overlap.
// ===========================================================
require_once 'config.php';
require_once 'audit_log.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    send_json(['error' => 'Invalid request.'], 400);
}

$template_id = (int)($body['template_id'] ?? 0);
$month = clean($body['month'] ?? '');
$range = clean($body['range'] ?? '1');
$confirm = isset($body['confirm']) ? (bool)$body['confirm'] : false;

if (!$template_id || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    send_json(['error' => 'Template and month are required. Use format YYYY-MM.'], 400);
}

$validRanges = ['1', '3', '6', '12'];
if (!in_array($range, $validRanges)) {
    send_json(['error' => 'Invalid range. Must be 1, 3, 6, or 12 months.'], 400);
}

try {
    // Load template
    $stmt = $pdo->prepare("SELECT template_id, doctor_id, dept_id, slot_duration, is_active, effective_from, effective_to FROM schedule_templates WHERE template_id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();

    if (!$template) {
        send_json(['error' => 'Schedule template not found.'], 404);
    }

    if ((int)$template['is_active'] !== 1) {
        send_json(['error' => 'Cannot generate slots from an inactive template.'], 400);
    }

    $startDate = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if (!$startDate) {
        send_json(['error' => 'Invalid month format.'], 400);
    }

    // Calculate end date based on range
    $endDate = (clone $startDate)->modify('last day of this month');
    if ($range > 1) {
        $endDate = (clone $startDate)->modify('+' . ($range - 1) . ' months');
        $endDate->modify('last day of ' . $endDate->format('F Y'));
    }

    // Check if template is effective for the requested period (overlap check)
    $templateEffectiveFrom = $template['effective_from'] ? new DateTime($template['effective_from']) : null;
    $templateEffectiveTo = $template['effective_to'] ? new DateTime($template['effective_to']) : null;

    if ($templateEffectiveFrom && $endDate < $templateEffectiveFrom) {
        send_json(['error' => 'Template is not effective until ' . $templateEffectiveFrom->format('Y-m-d') . '. The requested period ends before the template becomes active.'], 400);
    }
    if ($templateEffectiveTo && $startDate > $templateEffectiveTo) {
        send_json(['error' => 'Template is only effective until ' . $templateEffectiveTo->format('Y-m-d') . '. The requested period starts after the template has expired.'], 400);
    }

    $today = new DateTime('today');
    $nowTime = new DateTime('now');
    $doctorId = (int)$template['doctor_id'];

    // ==========================================================
    // LOAD WORKING SESSIONS (new sessions-based approach)
    // Falls back to legacy template_days + break fields
    // ==========================================================
    $sessionStmt = $pdo->prepare("SELECT day_of_week, start_time, end_time, sort_order FROM template_day_sessions WHERE template_id = ? ORDER BY day_of_week, sort_order");
    $sessionStmt->execute([$template_id]);
    $sessionRows = $sessionStmt->fetchAll();

    $sessionsByDay = [];
    if (!empty($sessionRows)) {
        foreach ($sessionRows as $row) {
            $day = (int)$row['day_of_week'];
            if (!isset($sessionsByDay[$day])) {
                $sessionsByDay[$day] = [];
            }
            $sessionsByDay[$day][] = $row;
        }
    } else {
        // Fallback: load from legacy template_days with break fields
        $dayStmt = $pdo->prepare("SELECT day_of_week, start_time, end_time, break_start, break_end FROM template_days WHERE template_id = ?");
        $dayStmt->execute([$template_id]);
        $dayRows = $dayStmt->fetchAll();
        foreach ($dayRows as $row) {
            if ((int)$row['is_working'] !== 1) continue;
            $day = (int)$row['day_of_week'];
            if (!isset($sessionsByDay[$day])) {
                $sessionsByDay[$day] = [];
            }
            $start = $row['start_time'];
            $end = $row['end_time'];
            $bs = $row['break_start'];
            $be = $row['break_end'];
            // Without break: single session. With break: two sessions.
            if ($bs && $be && $be > $bs) {
                if ($bs > $start) {
                    $sessionsByDay[$day][] = ['start_time' => $start, 'end_time' => $bs, 'sort_order' => 0];
                }
                if ($be < $end) {
                    $sessionsByDay[$day][] = ['start_time' => $be, 'end_time' => $end, 'sort_order' => 1];
                }
            } else {
                $sessionsByDay[$day][] = ['start_time' => $start, 'end_time' => $end, 'sort_order' => 0];
            }
        }
    }

    // Load template holidays
    $holidayStmt = $pdo->prepare("SELECT holiday_date FROM template_holidays WHERE template_id = ? AND holiday_date BETWEEN ? AND ?");
    $holidayStmt->execute([$template_id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $holidayDates = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $holidayMap = array_flip($holidayDates);

    // Load global hospital holidays
    $globalHolidayStmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $globalHolidayStmt->execute([$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $globalHolidayDates = $globalHolidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $globalHolidayMap = array_flip($globalHolidayDates);

    // Load doctor exceptions
    $exceptionStmt = $pdo->prepare("SELECT exception_date, is_working, start_time, end_time, break_start, break_end, note FROM schedule_exceptions WHERE doctor_id = ? AND exception_date BETWEEN ? AND ?");
    $exceptionStmt->execute([$doctorId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $exceptionRows = $exceptionStmt->fetchAll();
    $exceptionMap = [];
    foreach ($exceptionRows as $row) {
        $exceptionMap[$row['exception_date']] = $row;
    }

    // Check for existing slots in the period
    $existingStmt = $pdo->prepare("SELECT slot_date, COUNT(*) AS count, SUM(is_booked) AS booked_count FROM time_slots WHERE doctor_id = ? AND slot_date BETWEEN ? AND ? GROUP BY slot_date");
    $existingStmt->execute([$doctorId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $existingRows = $existingStmt->fetchAll();

    $existingDates = [];
    $totalExistingSlots = 0;
    $totalBookedSlots = 0;
    foreach ($existingRows as $row) {
        $existingDates[] = $row['slot_date'];
        $totalExistingSlots += (int)$row['count'];
        $totalBookedSlots += (int)$row['booked_count'];
    }

    if (!$confirm && !empty($existingDates)) {
        send_json([
            'success' => false,
            'need_confirmation' => true,
            'message' => 'Existing slots already exist for ' . count($existingDates) . ' day(s) in this period (' . $totalExistingSlots . ' total slots, ' . $totalBookedSlots . ' booked). Confirming will skip duplicates and keep existing bookings.',
            'duplicate_dates' => array_values($existingDates),
            'duplicate_count' => count($existingDates),
            'existing_slots' => $totalExistingSlots,
            'booked_slots' => $totalBookedSlots,
        ]);
    }

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO time_slots (dept_id, doctor_id, template_id, slot_date, start_time, end_time, capacity)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $created = 0;
    $skipped = 0;
    $holidaysSkipped = 0;
    $leaveSkipped = 0;
    $generatedDays = 0;
    $current = clone $startDate;
    $todayString = $today->format('Y-m-d');

    while ($current <= $endDate) {
        $weekday = (int)$current->format('w');
        $dateString = $current->format('Y-m-d');

        // Skip past dates
        if ($dateString < $todayString) {
            $current->modify('+1 day');
            continue;
        }

        // Check global holiday
        if (isset($globalHolidayMap[$dateString])) {
            $holidaysSkipped++;
            $current->modify('+1 day');
            continue;
        }

        // Check template holiday
        if (isset($holidayMap[$dateString])) {
            $holidaysSkipped++;
            $current->modify('+1 day');
            continue;
        }

        // Check doctor exception (override)
        $override = $exceptionMap[$dateString] ?? null;
        if ($override !== null) {
            if ((int)$override['is_working'] !== 1) {
                $leaveSkipped++;
                $current->modify('+1 day');
                continue;
            }
            // Override with custom times - build sessions from override
            $overrideSessions = [];
            $os = $override['start_time'];
            $oe = $override['end_time'];
            $obs = $override['break_start'];
            $obe = $override['break_end'];
            if ($os && $oe) {
                if ($obs && $obe && $obe > $obs) {
                    if ($obs > $os) {
                        $overrideSessions[] = ['start_time' => $os, 'end_time' => $obs];
                    }
                    if ($obe < $oe) {
                        $overrideSessions[] = ['start_time' => $obe, 'end_time' => $oe];
                    }
                } else {
                    $overrideSessions[] = ['start_time' => $os, 'end_time' => $oe];
                }
            }
            $daySessions = $overrideSessions;
        } else {
            // No override - use template sessions for this day of week
            $daySessions = $sessionsByDay[$weekday] ?? [];
        }

        if (empty($daySessions)) {
            $current->modify('+1 day');
            continue;
        }

        // Generate slots for each session
        $hasSlotsToday = false;
        foreach ($daySessions as $session) {
            $sessionStart = $session['start_time'];
            $sessionEnd = $session['end_time'];
            if (!$sessionStart || !$sessionEnd) continue;

            $currentMinute = time_to_minutes($sessionStart);
            $endMinute = time_to_minutes($sessionEnd);
            $duration = (int)$template['slot_duration'];

            while ($currentMinute + $duration <= $endMinute) {
                $slotStart = minutes_to_time($currentMinute);
                $slotEnd = minutes_to_time($currentMinute + $duration);

                // Skip past time slots for today
                if ($dateString === $todayString) {
                    $nowMinute = time_to_minutes($nowTime->format('H:i'));
                    if ($currentMinute <= $nowMinute) {
                        $currentMinute += $duration;
                        continue;
                    }
                }

                $insertStmt->execute([
                    $template['dept_id'],
                    $doctorId,
                    $template_id,
                    $dateString,
                    $slotStart,
                    $slotEnd,
                    1, // capacity = 1
                ]);

                if ($insertStmt->rowCount() > 0) {
                    $created++;
                } else {
                    $skipped++;
                }
                $currentMinute += $duration;
            }
        }

        $generatedDays++;
        $current->modify('+1 day');
    }

    $rangeLabel = $range == 1 ? '1 month' : "$range months";
    $summaryParts = [];
    $summaryParts[] = "<i class=\"fa-solid fa-check-circle\"></i> $created slots created";
    if ($skipped > 0) $summaryParts[] = "<i class=\"fa-solid fa-rotate-left\"></i> $skipped duplicates skipped";
    if ($holidaysSkipped > 0) $summaryParts[] = "<i class=\"fa-solid fa-calendar-xmark\"></i> $holidaysSkipped holidays skipped";
    if ($leaveSkipped > 0) $summaryParts[] = "<i class=\"fa-solid fa-user-clock\"></i> $leaveSkipped leave days skipped";
    $message = implode('<br>', $summaryParts);

    // Audit log
    audit_log(
        $pdo,
        (int)$_SESSION['user_id'],
        'slots_generated',
        'time_slots',
        $template_id,
        "Generated $created slots for template ID $template_id ($rangeLabel starting $month)",
        null,
        [
            'template_id' => $template_id,
            'month' => $month,
            'range' => $range,
            'created' => $created,
            'skipped' => $skipped,
            'holidays_skipped' => $holidaysSkipped,
            'leave_skipped' => $leaveSkipped,
            'generated_days' => $generatedDays,
        ]
    );

    send_json([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'holidays_skipped' => $holidaysSkipped,
        'leave_skipped' => $leaveSkipped,
        'generated_days' => $generatedDays,
        'month' => $month,
        'range' => $range,
        'message' => $message,
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error generating schedule slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to generate schedule slots', 'detail' => $e->getMessage()], 500);
}