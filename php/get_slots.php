<?php
// ===========================================================
// php/get_slots.php
// Returns available (unbooked) time slots for a department on a specific date.
// Checks all holiday types and doctor availability before returning slots.
// Returns status information if no slots available.
// CALLED BY: pages/book.html when a date is chosen
// ===========================================================

require_once 'config.php';
require_role('patient');

$dept_id = (int)($_GET['dept_id'] ?? 0);
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date    = clean($_GET['date'] ?? '');

// Validate date format strictly
if (!$dept_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    send_json(['slots' => [], 'status' => 'invalid', 'message' => 'Please select a valid department and date.']);
}

try {
    $doctorName = '';
    $weekday = (int)date('w', strtotime($date));

    // ==========================================================
    // CHECK 1: Hospital-wide holidays
    // ==========================================================
    $stmt = $pdo->prepare("SELECT name, note FROM holidays WHERE holiday_date = ?");
    $stmt->execute([$date]);
    $globalHoliday = $stmt->fetch();

    if ($globalHoliday) {
        send_json([
            'slots' => [],
            'status' => 'holiday',
            'message' => '<i class="fa-solid fa-calendar-xmark"></i> This date is a hospital holiday' .
                ($globalHoliday['name'] ? ': ' . htmlspecialchars($globalHoliday['name']) : '') . '.',
        ]);
    }

    // ==========================================================
    // CHECK 2: If a specific doctor was requested, check their availability
    // ==========================================================
    if ($doctor_id > 0) {
        $stmt = $pdo->prepare("SELECT full_name, specialization FROM doctors WHERE doctor_id = ? AND is_active = 1");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();
        if ($doctor) {
            $doctorName = $doctor['full_name'];
        }

        // Check doctor-specific exception for this date
        $stmt = $pdo->prepare("SELECT is_working, note FROM schedule_exceptions WHERE doctor_id = ? AND exception_date = ?");
        $stmt->execute([$doctor_id, $date]);
        $exception = $stmt->fetch();

        if ($exception) {
            if ((int)$exception['is_working'] !== 1) {
                $note = $exception['note'] ?: 'is unavailable';
                send_json([
                    'slots' => [],
                    'status' => 'unavailable',
                    'message' => '<i class="fa-solid fa-user-clock"></i> ' . htmlspecialchars($doctorName) . ' ' . htmlspecialchars($note) . ' on this date.',
                    'doctor_name' => $doctorName,
                ]);
            }
            // Exception is working - will use its custom hours if available
        }

        // Check if doctor is working on this day of week based on active templates
        $stmt = $pdo->prepare("
            SELECT t.template_id, td.is_working
            FROM schedule_templates t
            JOIN template_days td ON t.template_id = td.template_id
            WHERE t.doctor_id = ? AND t.is_active = 1 AND td.day_of_week = ?
              AND (t.effective_from IS NULL OR t.effective_from <= ?)
              AND (t.effective_to IS NULL OR t.effective_to >= ?)
            LIMIT 1
        ");
        $stmt->execute([$doctor_id, $weekday, $date, $date]);
        $dayConfig = $stmt->fetch();

        if (!$dayConfig || (int)$dayConfig['is_working'] !== 1) {
            $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            send_json([
                'slots' => [],
                'status' => 'not_working',
                'message' => '<i class="fa-solid fa-calendar-day"></i> ' . htmlspecialchars($doctorName) .
                    ' does not work on <strong>' . $dayNames[$weekday] . '</strong>.',
                'doctor_name' => $doctorName,
            ]);
        }

        // Check template-specific holidays
        $stmt = $pdo->prepare("
            SELECT th.holiday_date
            FROM schedule_templates t
            JOIN template_holidays th ON t.template_id = th.template_id
            WHERE t.doctor_id = ? AND t.is_active = 1 AND th.holiday_date = ?
            LIMIT 1
        ");
        $stmt->execute([$doctor_id, $date]);
        if ($stmt->fetch()) {
            send_json([
                'slots' => [],
                'status' => 'holiday',
                'message' => '<i class="fa-solid fa-calendar-xmark"></i> ' . htmlspecialchars($doctorName) .
                    ' has a scheduled holiday on this date.',
                'doctor_name' => $doctorName,
            ]);
        }
    } else {
        // No specific doctor - check if ANY doctor in department is available
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM time_slots ts
            WHERE ts.dept_id = ? AND ts.slot_date = ? AND ts.is_active = 1 AND ts.is_booked = 0
              AND (ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.start_time > CURTIME()))
        ");
        $stmt->execute([$dept_id, $date]);
        $slotCount = (int)$stmt->fetchColumn();

        if ($slotCount === 0) {
            // Check if it's a weekend/holiday for the department's doctors
            $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $msg = '<i class="fa-solid fa-calendar-day"></i> No appointments available on <strong>' . $dayNames[$weekday] . '</strong>.';
            send_json([
                'slots' => [],
                'status' => 'unavailable',
                'message' => $msg,
            ]);
        }
    }

    // ==========================================================
    // FINAL: Return available slots
    // ==========================================================
    $filters = [
        'ts.dept_id = ?',
        'ts.slot_date = ?',
        'ts.is_active = 1',
        'ts.is_booked = 0',
        '(ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.start_time > CURTIME()))',
    ];
    $params = [$dept_id, $date];

    if ($doctor_id > 0) {
        // Specific doctor selected - show only their slots (or unassigned slots)
        $filters[] = '(ts.doctor_id = ? OR ts.doctor_id IS NULL)';
        $params[] = $doctor_id;
    }
    // When no doctor selected (doctor_id = 0), show ALL slots for all doctors in department

    $sql = "
        SELECT
            ts.slot_id,
            ts.start_time,
            ts.end_time,
            ts.capacity,
            ts.booked_count,
            ts.doctor_id,
            doc.full_name AS doctor_name,
            doc.specialization AS doctor_specialization
        FROM time_slots ts
        LEFT JOIN doctors doc ON ts.doctor_id = doc.doctor_id
        WHERE " . implode(' AND ', $filters) . "
        ORDER BY ts.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $slots = $stmt->fetchAll();

    if (empty($slots)) {
        $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        if ($doctor_id > 0 && $doctorName) {
            send_json([
                'slots' => [],
                'status' => 'fully_booked',
                'message' => '<i class="fa-solid fa-calendar-check"></i> ' . htmlspecialchars($doctorName) .
                    ' has no available slots on this date. Try another date.',
                'doctor_name' => $doctorName,
            ]);
        } else {
            send_json([
                'slots' => [],
                'status' => 'fully_booked',
                'message' => '<i class="fa-solid fa-calendar-check"></i> All slots are booked for this date. Try another date.',
            ]);
        }
    }

    // Calculate availability status
    $totalCapacity = 0;
    $totalBooked = 0;
    foreach ($slots as $slot) {
        $totalCapacity += (int)($slot['capacity'] ?? 1);
        $totalBooked += (int)($slot['booked_count'] ?? 0);
    }
    $available = count($slots);
    $status = 'available';
    if ($available <= 3) {
        $status = 'few_slots';
    }

    send_json([
        'slots' => $slots,
        'status' => $status,
        'available_count' => $available,
        'message' => null,
        'doctor_name' => $doctorName,
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch time slots'], 500);
}