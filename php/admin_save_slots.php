<?php
// Generate multiple time slots in bulk, or toggle a single slot.
// Department scope: creates identical slots for every active doctor in the department.
// Doctor scope: creates slots for that doctor only.
require_once 'config.php';
require_role('admin');

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) send_json(['error' => 'Invalid request.'], 400);

    // --- Toggle a single slot active/inactive ---
    if (isset($body['slot_id']) && isset($body['toggle_active'])) {
        $stmt = $pdo->prepare("UPDATE time_slots SET is_active=? WHERE slot_id=? AND is_booked=0");
        $stmt->execute([(int)$body['toggle_active'], (int)$body['slot_id']]);
        send_json(['success' => true]);
    }

    // --- Bulk slot generation ---
    $dept_id    = (int)($body['dept_id'] ?? 0);
    $doctor_id  = isset($body['doctor_id']) ? (int)$body['doctor_id'] : 0;
    $start_date = isset($body['start_date']) ? clean((string)$body['start_date']) : (isset($body['date']) ? clean((string)$body['date']) : '');
    $end_date   = isset($body['end_date']) ? clean((string)$body['end_date']) : '';
    $start_time = isset($body['start']) ? clean((string)$body['start']) : '';
    $end_time   = isset($body['end']) ? clean((string)$body['end']) : '';
    $duration   = (int)($body['duration'] ?? 10);
    $capacity   = 1;

    if ((!$dept_id && !$doctor_id) || !$start_date || !$start_time || !$end_time || !$duration) {
        send_json(['error' => 'Doctor or department, start date, times, and duration are required.'], 400);
    }

    if (!$end_date) {
        $end_date = $start_date;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        send_json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
    }

    // Resolve target doctors: one doctor, or all active doctors in a department.
    $targetDoctors = [];

    if ($doctor_id > 0) {
        $stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE doctor_id = ? AND is_active = 1");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doctor) {
            send_json(['error' => 'Selected doctor is not valid.'], 400);
        }
        $targetDoctors[] = $doctor;
        $dept_id = (int)$doctor['dept_id'];
    } else {
        $stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE dept_id = ? AND is_active = 1 ORDER BY doctor_id");
        $stmt->execute([$dept_id]);
        $targetDoctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($targetDoctors)) {
            send_json(['error' => 'No active doctors found in this department.'], 400);
        }
    }

    $today = date('Y-m-d');
    if (strtotime($end_date) < strtotime($today)) {
        send_json(['error' => 'End date cannot be in the past.'], 400);
    }

    if (strtotime($start_date) > strtotime($end_date)) {
        send_json(['error' => 'Start date cannot be after end date.'], 400);
    }

    $days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
    if ($days_diff > 90) {
        send_json(['error' => 'Date range cannot exceed 90 days.'], 400);
    }

    $toMins = function(string $t): int {
        [$h, $m] = explode(':', $t);
        return (int)$h * 60 + (int)$m;
    };

    $toTime = function(int $mins): string {
        return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
    };

    $startMins = $toMins($start_time);
    $endMins   = $toMins($end_time);

    if ($endMins <= $startMins) {
        send_json(['error' => 'End time must be after start time.'], 400);
    }

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO time_slots (dept_id, doctor_id, slot_date, start_time, end_time, capacity)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $duplicateStmt = $pdo->prepare(
        "SELECT 1 FROM time_slots WHERE dept_id = ? AND doctor_id = ? AND slot_date = ? AND start_time = ?"
    );

    $created = 0;
    $skipped_dates = [];

    $current_date = $start_date;
    while (strtotime($current_date) <= strtotime($end_date)) {
        foreach ($targetDoctors as $target) {
            $slotDeptId   = (int)$target['dept_id'];
            $slotDoctorId = (int)$target['doctor_id'];

            $current = $startMins;
            if ($current_date === $today) {
                $now = new DateTime('now');
                $nowMins = (int)$now->format('H') * 60 + (int)$now->format('i');
                if ($current <= $nowMins) {
                    $remainder = ($nowMins - $current) % $duration;
                    $current = $nowMins + ($remainder === 0 ? $duration : ($duration - $remainder));
                }
            }

            $day_created = 0;
            while ($current + $duration <= $endMins) {
                $slotStart = $toTime($current);
                $slotEnd   = $toTime($current + $duration);

                $duplicateStmt->execute([$slotDeptId, $slotDoctorId, $current_date, $slotStart]);
                if ($duplicateStmt->fetch()) {
                    $current += $duration;
                    continue;
                }

                $insertStmt->execute([$slotDeptId, $slotDoctorId, $current_date, $slotStart, $slotEnd, $capacity]);

                if ($insertStmt->rowCount() > 0) {
                    $created++;
                    $day_created++;
                }

                $current += $duration;
            }

            if ($day_created === 0 && $current_date !== $today) {
                $skipped_dates[] = $current_date;
            }
        }

        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }

    if ($created === 0) {
        send_json(['error' => 'No new slots created. All slots may already exist for this date range.'], 400);
    }

    $doctorCount = count($targetDoctors);
    $scopeLabel  = $doctor_id > 0 ? 'doctor' : "department ($doctorCount doctor" . ($doctorCount !== 1 ? 's' : '') . ")";

    $response = [
        'success' => true,
        'created' => $created,
        'scope'   => $scopeLabel,
        'date_range' => "$start_date to $end_date",
        'days_processed' => $days_diff + 1,
    ];

    if (!empty($skipped_dates)) {
        $response['skipped_dates'] = array_values(array_unique($skipped_dates));
        $response['message'] = "Created $created slot(s) for $scopeLabel. Some dates were skipped (slots already exist).";
    } else {
        $response['message'] = "Successfully created $created slot(s) for $scopeLabel.";
    }

    send_json($response);
} catch (PDOException $e) {
    error_log('[HAMS] PDO error in admin_save_slots: ' . $e->getMessage());
    send_json(['error' => 'Database error', 'detail' => $e->getMessage()], 500);
} catch (TypeError $e) {
    error_log('[HAMS] Type error in admin_save_slots: ' . $e->getMessage());
    send_json(['error' => 'Type error', 'detail' => $e->getMessage()], 500);
}
?>
