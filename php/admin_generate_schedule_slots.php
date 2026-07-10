<?php
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    send_json(['error' => 'Invalid request.'], 400);
}

$template_id = (int)($body['template_id'] ?? 0);
$month = clean($body['month'] ?? '');
$confirm = isset($body['confirm']) ? (bool)$body['confirm'] : false;

if (!$template_id || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    send_json(['error' => 'Template and month are required. Use format YYYY-MM.'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT template_id, doctor_id, dept_id, slot_duration, is_active FROM schedule_templates WHERE template_id = ?");
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

    $endDate = (clone $startDate)->modify('last day of this month');
    $today = new DateTime('today');
    $nowTime = new DateTime('now');

    $dayStmt = $pdo->prepare("SELECT day_of_week, is_working, start_time, end_time, break_start, break_end FROM template_days WHERE template_id = ?");
    $dayStmt->execute([$template_id]);
    $dayRows = $dayStmt->fetchAll();

    $days = [];
    foreach ($dayRows as $row) {
        $days[(int)$row['day_of_week']] = $row;
    }

    // Ensure we have rows for all 7 days; missing day = not working.
    for ($d = 0; $d <= 6; $d++) {
        if (!isset($days[$d])) {
            $days[$d] = [
                'day_of_week' => $d,
                'is_working' => 0,
                'start_time' => null,
                'end_time' => null,
                'break_start' => null,
                'break_end' => null,
            ];
        }
    }

    $holidayStmt = $pdo->prepare("SELECT holiday_date FROM template_holidays WHERE template_id = ?");
    $holidayStmt->execute([$template_id]);
    $holidayDates = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $holidayMap = array_flip($holidayDates);

    $globalHolidayStmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $globalHolidayStmt->execute([$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $globalHolidayDates = $globalHolidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $globalHolidayMap = array_flip($globalHolidayDates);

    $exceptionStmt = $pdo->prepare("SELECT exception_date, is_working, start_time, end_time, break_start, break_end FROM schedule_exceptions WHERE doctor_id = ? AND exception_date BETWEEN ? AND ?");
    $exceptionStmt->execute([$template['doctor_id'], $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $exceptionRows = $exceptionStmt->fetchAll();
    $exceptionMap = [];
    foreach ($exceptionRows as $row) {
        $exceptionMap[$row['exception_date']] = $row;
    }

    $existingStmt = $pdo->prepare("SELECT slot_date, COUNT(*) AS count FROM time_slots WHERE doctor_id = ? AND slot_date BETWEEN ? AND ? GROUP BY slot_date");
    $existingStmt->execute([$template['doctor_id'], $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $existingRows = $existingStmt->fetchAll();

    $duplicates = [];
    foreach ($existingRows as $row) {
        $duplicates[] = $row['slot_date'];
    }

    if (!$confirm && !empty($duplicates)) {
        send_json([
            'success' => false,
            'need_confirmation' => true,
            'message' => 'Existing slots already exist for some days in this month. Confirm regeneration to skip duplicates and keep existing bookings.',
            'duplicate_dates' => array_values($duplicates),
            'duplicate_count' => count($duplicates),
        ]);
    }

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO time_slots (dept_id, doctor_id, template_id, slot_date, start_time, end_time, capacity)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $created = 0;
    $skipped = 0;
    $generatedDays = 0;
    $current = clone $startDate;
    $todayString = $today->format('Y-m-d');

    while ($current <= $endDate) {
        $weekday = (int)$current->format('w');
        $dateString = $current->format('Y-m-d');

        if ($dateString < $todayString) {
            $current->modify('+1 day');
            continue;
        }

        if (isset($globalHolidayMap[$dateString]) || isset($holidayMap[$dateString])) {
            $current->modify('+1 day');
            continue;
        }

        $override = $exceptionMap[$dateString] ?? null;
        if ($override !== null) {
            if ((int)$override['is_working'] !== 1) {
                $current->modify('+1 day');
                continue;
            }
            $day = [
                'start_time' => $override['start_time'],
                'end_time' => $override['end_time'],
                'break_start' => $override['break_start'],
                'break_end' => $override['break_end'],
            ];
        } else {
            $day = $days[$weekday];
            if ((int)$day['is_working'] !== 1) {
                $current->modify('+1 day');
                continue;
            }
        }

        $ranges = [];
        $startTime = $day['start_time'];
        $endTime = $day['end_time'];
        $breakStart = $day['break_start'];
        $breakEnd = $day['break_end'];

        if (!$startTime || !$endTime) {
            $current->modify('+1 day');
            continue;
        }

        if ($breakStart && $breakEnd && time_to_minutes($breakEnd) > time_to_minutes($breakStart)) {
            if (time_to_minutes($breakStart) > time_to_minutes($startTime)) {
                $ranges[] = [$startTime, $breakStart];
            }
            if (time_to_minutes($breakEnd) < time_to_minutes($endTime)) {
                $ranges[] = [$breakEnd, $endTime];
            }
        } else {
            $ranges[] = [$startTime, $endTime];
        }

        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            $currentMinute = time_to_minutes($rangeStart);
            $endMinute = time_to_minutes($rangeEnd);
            while ($currentMinute + (int)$template['slot_duration'] <= $endMinute) {
                $slotStart = minutes_to_time($currentMinute);
                $slotEnd = minutes_to_time($currentMinute + (int)$template['slot_duration']);

                if ($dateString === $todayString) {
                    $nowMinute = time_to_minutes($nowTime->format('H:i'));
                    if ($currentMinute <= $nowMinute) {
                        $currentMinute += (int)$template['slot_duration'];
                        continue;
                    }
                }

                // Single-patient slots (capacity = 1) to simplify booking flow and avoid multi-seat complexity
                $insertStmt->execute([
                    $template['dept_id'],
                    $template['doctor_id'],
                    $template_id,
                    $dateString,
                    $slotStart,
                    $slotEnd,
                    1,
                ]);

                if ($insertStmt->rowCount() > 0) {
                    $created++;
                } else {
                    $skipped++;
                }
                $currentMinute += (int)$template['slot_duration'];
            }
        }

        $generatedDays++;
        $current->modify('+1 day');
    }

    $message = "Generated $created slots for {$generatedDays} active day(s).";
    if ($skipped > 0) {
        $message .= " Skipped $skipped duplicate slot(s).";
    }

    send_json([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'month' => $month,
        'message' => $message,
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error generating schedule slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to generate schedule slots', 'detail' => $e->getMessage()], 500);
}
