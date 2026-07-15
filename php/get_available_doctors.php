<?php
// ===========================================================
// php/get_available_doctors.php
// Returns doctors in a department who are available for a
// specific date and time slot. Used by the booking page
// for optional manual doctor selection.
// CALLED BY: pages/book.html when patient chooses "Select specific doctor"
// METHOD: GET with dept_id, slot_date, and optional start_time
// RETURNS: JSON array of doctors with availability status
// ===========================================================

require_once 'config.php';
require_role('patient');

$dept_id   = (int)($_GET['dept_id']   ?? 0);
$slot_date = clean($_GET['slot_date'] ?? '');
$start_time = clean($_GET['start_time'] ?? ''); // optional — if slot selected

if (!$dept_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $slot_date)) {
    send_json([], 400);
}

$weekday = (int)date('w', strtotime($slot_date));

try {
    // Step 1: Get all active doctors in this department
    $stmt = $pdo->prepare("
        SELECT d.doctor_id, d.full_name, d.specialization
        FROM doctors d
        WHERE d.dept_id = :dept_id AND d.is_active = 1
        ORDER BY d.full_name ASC
    ");
    $stmt->execute([':dept_id' => $dept_id]);
    $allDoctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allDoctors)) {
        send_json([]);
    }

    $doctorIds = array_column($allDoctors, 'doctor_id');

    // Step 2: Check schedule_exceptions for date-specific unavailability
    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
    $stmt = $pdo->prepare("
        SELECT doctor_id
        FROM schedule_exceptions
        WHERE doctor_id IN ($placeholders)
          AND exception_date = ?
          AND is_working = 0
    ");
    $params = $doctorIds;
    $params[] = $slot_date;
    $stmt->execute($params);
    $unavailableDoctorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Step 3: Check if date is a hospital holiday
    $stmt = $pdo->prepare("SELECT holiday_date FROM holidays WHERE holiday_date = ?");
    $stmt->execute([$slot_date]);
    $isHoliday = $stmt->fetchColumn();

    // Step 4: If start_time provided, check which doctors are already booked
    $bookedDoctorIds = [];
    if ($start_time) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.doctor_id
            FROM appointments a
            JOIN time_slots s ON a.slot_id = s.slot_id
            WHERE a.doctor_id IN ($placeholders)
              AND s.slot_date = ?
              AND s.start_time = ?
              AND a.status NOT IN ('cancelled')
        ");
        $params2 = $doctorIds;
        $params2[] = $slot_date;
        $params2[] = $start_time;
        $stmt->execute($params2);
        $bookedDoctorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Step 5: Build availability status for each doctor
    $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $result = [];

    foreach ($allDoctors as $doc) {
        $reasons = [];

        // Check holiday
        if ($isHoliday) {
            $reasons[] = 'Hospital holiday';
        }

        // Check doctor-specific exception
        if (in_array($doc['doctor_id'], $unavailableDoctorIds)) {
            $reasons[] = 'On leave / unavailable';
        }

        // Check if doctor works on this weekday (schedule templates)
        $stmt = $pdo->prepare("
            SELECT td.is_working
            FROM schedule_templates t
            JOIN template_days td ON t.template_id = td.template_id
            WHERE t.doctor_id = :doctor_id AND t.is_active = 1 AND td.day_of_week = :dow
              AND (t.effective_from IS NULL OR t.effective_from <= :sdate)
              AND (t.effective_to IS NULL OR t.effective_to >= :sdate)
            LIMIT 1
        ");
        $stmt->execute([
            ':doctor_id' => $doc['doctor_id'],
            ':dow'       => $weekday,
            ':sdate'     => $slot_date,
        ]);
        $dayConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dayConfig || (int)$dayConfig['is_working'] !== 1) {
            $reasons[] = 'Does not work on ' . $dayNames[$weekday];
        }

        // Check template-specific holidays
        $stmt = $pdo->prepare("
            SELECT th.holiday_date
            FROM schedule_templates t
            JOIN template_holidays th ON t.template_id = th.template_id
            WHERE t.doctor_id = :doctor_id AND t.is_active = 1 AND th.holiday_date = :sdate2
            LIMIT 1
        ");
        $stmt->execute([':doctor_id' => $doc['doctor_id'], ':sdate2' => $slot_date]);
        if ($stmt->fetch()) {
            $reasons[] = 'Scheduled holiday';
        }

        // Check if already booked for this slot time
        if (in_array($doc['doctor_id'], $bookedDoctorIds)) {
            $reasons[] = 'Already booked for this time';
        }

        $available = empty($reasons);
        $result[] = [
            'doctor_id'   => (int)$doc['doctor_id'],
            'full_name'   => $doc['full_name'],
            'specialization' => $doc['specialization'],
            'available'   => $available,
            'unavailable_reason' => $available ? null : implode('; ', $reasons),
        ];
    }

    send_json($result);

} catch (PDOException $e) {
    error_log('[HAMS] Error fetching available doctors: ' . $e->getMessage());
    send_json([], 500);
}