<?php
// ============================================================
// php/generate_slots.php
// Generates time slots from schedule templates for the next 7 days.
// This script is safe to run multiple times because it uses
// INSERT IGNORE and keeps existing bookings intact.
//
// HOW TO RUN:
//   Manual:   visit http://localhost/hams2/php/generate_slots.php
//   Cron:     0 0 * * * php /path/to/hams2/php/generate_slots.php
// ============================================================

require_once 'config.php';
if (PHP_SAPI !== 'cli') {
    require_role('admin');
}

$days_ahead = 7;
$generated = 0;
$skipped = 0;

function timeToMins(string $t): int {
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}

function minsToTime(int $mins): string {
    return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
}

$insert = $pdo->prepare("
    INSERT IGNORE INTO time_slots (dept_id, doctor_id, template_id, slot_date, start_time, end_time, capacity)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$templateStmt = $pdo->query("
    SELECT template_id, doctor_id, dept_id, slot_duration
    FROM schedule_templates
    WHERE is_active = 1
");
$templates = $templateStmt->fetchAll();

$templatesByDay = [];
$dayStmt = $pdo->prepare("
    SELECT template_id, day_of_week, is_working, start_time, end_time, break_start, break_end
    FROM template_days
    WHERE template_id = ?
");

$globalHolidayStmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN DATE(NOW()) AND DATE_ADD(DATE(NOW()), INTERVAL ? DAY)");
$globalHolidayStmt->execute([$days_ahead]);
$globalHolidayDates = $globalHolidayStmt->fetchAll(PDO::FETCH_COLUMN);
$globalHolidayMap = array_flip($globalHolidayDates);

$templateHolidayStmt = $pdo->prepare("SELECT holiday_date FROM template_holidays WHERE template_id = ? AND holiday_date BETWEEN DATE(NOW()) AND DATE_ADD(DATE(NOW()), INTERVAL ? DAY)");

$exceptionStmt = $pdo->prepare("SELECT exception_date, is_working, start_time, end_time, break_start, break_end FROM schedule_exceptions WHERE doctor_id = ? AND exception_date BETWEEN DATE(NOW()) AND DATE_ADD(DATE(NOW()), INTERVAL ? DAY)");

$exceptionsByDoctor = [];

foreach ($templates as $template) {
    $templateHolidayStmt->execute([$template['template_id'], $days_ahead]);
    $templateHolidayDates = $templateHolidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $templateHolidayMap = array_flip($templateHolidayDates);
    $template['template_holidays'] = $templateHolidayMap;

    $exceptionStmt->execute([$template['doctor_id'], $days_ahead]);
    $exceptions = $exceptionStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($exceptions as $exception) {
        $exceptionsByDoctor[$template['doctor_id']][$exception['exception_date']] = $exception;
    }

    $dayStmt->execute([$template['template_id']]);
    $dayRows = $dayStmt->fetchAll();
    foreach ($dayRows as $dayRow) {
        $dayRow['doctor_id'] = $template['doctor_id'];
        $dayRow['dept_id'] = $template['dept_id'];
        $dayRow['template_holidays'] = $template['template_holidays'];
        if ((int)$dayRow['is_working'] !== 1) {
            continue;
        }
        $templatesByDay[(int)$dayRow['day_of_week']][] = array_merge($template, $dayRow);
    }
}

for ($i = 0; $i < $days_ahead; $i++) {
    $date = date('Y-m-d', strtotime("+{$i} days"));
    $weekday = (int)date('w', strtotime($date));
    $currentTemplates = $templatesByDay[$weekday] ?? [];

    foreach ($currentTemplates as $template) {
        if (isset($globalHolidayMap[$date])) {
            $skipped++;
            continue;
        }

        if (isset($template['template_holidays'][$date])) {
            $skipped++;
            continue;
        }

        $exception = $exceptionsByDoctor[$template['doctor_id']][$date] ?? null;
        $isWorking = (int)$template['is_working'];
        $startTime = $template['start_time'];
        $endTime = $template['end_time'];
        $breakStart = $template['break_start'];
        $breakEnd = $template['break_end'];
        $blockedRange = null;

        if ($exception) {
            // Check if this is a block_time exception
            $noteData = json_decode($exception['note'] ?? '', true);
            if (isset($noteData['type']) && $noteData['type'] === 'block_time') {
                $blockedRange = [
                    'start' => timeToMins($noteData['block_start']),
                    'end' => timeToMins($noteData['block_end'])
                ];
                // Keep template times but will filter blocked slots later
            } else {
                $isWorking = (int)$exception['is_working'];
                if ($exception['start_time']) {
                    $startTime = $exception['start_time'];
                }
                if ($exception['end_time']) {
                    $endTime = $exception['end_time'];
                }
                $breakStart = $exception['break_start'] ?: $breakStart;
                $breakEnd = $exception['break_end'] ?: $breakEnd;
            }
        }

        if ($isWorking !== 1 || !$startTime || !$endTime) {
            $skipped++;
            continue;
        }

        $startMins = timeToMins($startTime);
        $endMins = timeToMins($endTime);
        $duration = (int)$template['slot_duration'];
        // Fixed single-patient slots to simplify logic
        $capacity = 1;

        $periods = [];
        if ($breakStart && $breakEnd && timeToMins($breakEnd) > timeToMins($breakStart)) {
            if (timeToMins($breakStart) > $startMins) {
                $periods[] = [$startMins, timeToMins($breakStart)];
            }
            if (timeToMins($breakEnd) < $endMins) {
                $periods[] = [timeToMins($breakEnd), $endMins];
            }
        } else {
            $periods[] = [$startMins, $endMins];
        }

        foreach ($periods as [$periodStart, $periodEnd]) {
            $slotStart = $periodStart;
            while ($slotStart + $duration <= $periodEnd) {
                // Check if slot falls within blocked time range
                if ($blockedRange !== null) {
                    if ($slotStart >= $blockedRange['start'] && $slotStart + $duration <= $blockedRange['end']) {
                        $skipped++;
                        $slotStart += $duration;
                        continue;
                    }
                }

                // Check if slot already exists before insertion
                $checkStmt = $pdo->prepare("
                    SELECT slot_id FROM time_slots 
                    WHERE dept_id = ? AND doctor_id = ? AND slot_date = ? 
                    AND start_time = ? AND end_time = ?
                ");
                $checkStmt->execute([
                    $template['dept_id'],
                    $template['doctor_id'],
                    $date,
                    minsToTime($slotStart),
                    minsToTime($slotStart + $duration),
                ]);
                
                if ($checkStmt->fetch()) {
                    $skipped++;
                } else {
                    $insert->execute([
                        $template['dept_id'],
                        $template['doctor_id'],
                        $template['template_id'],
                        $date,
                        minsToTime($slotStart),
                        minsToTime($slotStart + $duration),
                        $capacity,
                    ]);

                    if ($insert->rowCount() > 0) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }

                $slotStart += $duration;
            }
        }
    }
}

send_json([
    'success' => true,
    'generated' => $generated,
    'skipped' => $skipped,
    'message' => "{$generated} slots created, {$skipped} already existed.",
]);
?>