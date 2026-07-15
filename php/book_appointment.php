<?php
// ===========================================================
// php/book_appointment.php
// Saves a new appointment to the database.
// CALLED BY: pages/book.html confirm button via fetch()
// METHOD: POST, receives JSON body
// RETURNS: { "success": true } or { "error": "message" }
//
// BOOKING FLOW SUPPORT (Updated):
// Patients can either:
//   A) Let the system auto-assign the best available doctor (default)
//   B) Manually select a specific doctor
// Both methods use the same availability rules to prevent conflicts.
// ===========================================================

require_once 'config.php';
require_role('patient');

// Log incoming request for debugging
error_log('[HAMS] Booking request received from user_id: ' . ($_SESSION['user_id'] ?? 'unknown'));

$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    error_log('[HAMS] Invalid request body - JSON decode failed');
    send_json(['error' => 'Invalid request.'], 400);
}

error_log('[HAMS] Request data: ' . json_encode($body));

$uid       = $_SESSION['user_id'];
$slot_id   = (int)($body['slot_id']            ?? 0);
$dept_id   = (int)($body['dept_id']            ?? 0);
$doctor_id_from_req = isset($body['doctor_id']) ? (int)$body['doctor_id'] : null;
$family_id = (int)($body['family_profile_id']  ?? 0);
$notes     = clean($body['notes']              ?? '');

if (!$slot_id || !$dept_id) {
    send_json(['error' => 'Missing required fields.'], 400);
}

// --- Verify the slot is still available ---
$stmt = $pdo->prepare("
    SELECT slot_id, slot_date, start_time, is_booked, doctor_id, dept_id
    FROM time_slots
    WHERE slot_id = ? AND is_active = 1 AND is_booked = 0
");
$stmt->execute([$slot_id]);
$slot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slot) {
    send_json(['error' => 'That slot is no longer available. Please choose another.'], 409);
}

if ($slot['dept_id'] != $dept_id) {
    send_json(['error' => 'Selected department does not match the slot.'], 400);
}

// Server-side: prevent booking past slots
$today = date('Y-m-d');
$nowTime = date('H:i:s');

if (strtotime($slot['slot_date']) < strtotime($today) ||
    ($slot['slot_date'] === $today && $slot['start_time'] <= $nowTime)) {
    send_json(['error' => 'Cannot book a past slot. Please choose another.'], 400);
}

// =====================================================
// DOCTOR ASSIGNMENT
// =====================================================
$doctor_id = null;

// Option B: Patient manually selected a doctor
if ($doctor_id_from_req !== null && $doctor_id_from_req > 0) {
    $doctor_id = $doctor_id_from_req;

    // Validate: doctor must exist, be active, and belong to the same department
    $stmt = $pdo->prepare("
        SELECT doctor_id FROM doctors
        WHERE doctor_id = :did AND is_active = 1 AND dept_id = :dept_id
    ");
    $stmt->execute([':did' => $doctor_id, ':dept_id' => $dept_id]);
    if (!$stmt->fetch()) {
        send_json(['error' => 'Selected doctor is not available in this department.'], 400);
    }

    // Validate: doctor must not have an unavailability/leave for this date
    $stmt = $pdo->prepare("
        SELECT doctor_id FROM schedule_exceptions
        WHERE doctor_id = :did AND exception_date = :sdate AND is_working = 0
    ");
    $stmt->execute([':did' => $doctor_id, ':sdate' => $slot['slot_date']]);
    if ($stmt->fetch()) {
        send_json([
            'error' => 'The selected doctor is no longer available for this time slot. Please choose another doctor or switch to automatic assignment.',
        ], 409);
    }

    // Validate: doctor must not already have an appointment at this slot time
    $stmt = $pdo->prepare("
        SELECT a.appt_id
        FROM appointments a
        JOIN time_slots s ON a.slot_id = s.slot_id
        WHERE a.doctor_id = :did
          AND s.slot_date = :sdate
          AND s.start_time = :stime
          AND a.status NOT IN ('cancelled')
    ");
    $stmt->execute([
        ':did'   => $doctor_id,
        ':sdate' => $slot['slot_date'],
        ':stime' => $slot['start_time'],
    ]);
    if ($stmt->fetch()) {
        send_json([
            'error' => 'The selected doctor is no longer available for this time slot. Please choose another doctor or switch to automatic assignment.',
        ], 409);
    }
} else {
    // Option A: Auto-assignment (default behavior)
    // If the slot already has a bound doctor, use that one.
    if (!empty($slot['doctor_id'])) {
        $doctor_id = $slot['doctor_id'];
    } else {
        try {
            // Step 1: Get all active doctors in this department
            $stmt = $pdo->prepare("
                SELECT d.doctor_id, d.full_name
                FROM doctors d
                WHERE d.dept_id = :dept_id AND d.is_active = 1
            ");
            $stmt->execute([':dept_id' => $slot['dept_id']]);
            $allDoctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($allDoctors)) {
                error_log('[HAMS] No doctors found in department ' . $slot['dept_id']);
                send_json(['error' => 'No doctors available in this department. Please contact support.'], 400);
            }

            $doctorIds = array_column($allDoctors, 'doctor_id');
            $slotDate = $slot['slot_date'];
            $slotTime = $slot['start_time'];

            // Step 2: Exclude doctors with unavailability/leave for this slot time
            $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
            $stmt = $pdo->prepare("
                SELECT doctor_id
                FROM schedule_exceptions
                WHERE doctor_id IN ($placeholders)
                  AND exception_date = ?
                  AND is_working = 0
            ");
            $params = $doctorIds;
            $params[] = $slotDate;
            $stmt->execute($params);
            $unavailableDoctors = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Filter out unavailable doctors
            $availableDoctors = array_filter($allDoctors, function($doc) use ($unavailableDoctors) {
                return !in_array($doc['doctor_id'], $unavailableDoctors);
            });

            if (empty($availableDoctors)) {
                send_json(['error' => 'All doctors are unavailable on this date. Please choose another.'], 400);
            }

            // Step 3: Exclude doctors who already have an appointment during this slot
            $availableIds = array_column($availableDoctors, 'doctor_id');
            $placeholders2 = implode(',', array_fill(0, count($availableIds), '?'));

            $stmt = $pdo->prepare("
                SELECT a.doctor_id
                FROM appointments a
                JOIN time_slots s ON a.slot_id = s.slot_id
                WHERE a.doctor_id IN ($placeholders2)
                  AND s.slot_date = ?
                  AND s.start_time = ?
                  AND a.status NOT IN ('cancelled')
            ");
            $params2 = $availableIds;
            $params2[] = $slotDate;
            $params2[] = $slotTime;
            $stmt->execute($params2);
            $busyDoctorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($busyDoctorIds)) {
                $availableDoctors = array_filter($availableDoctors, function($doc) use ($busyDoctorIds) {
                    return !in_array($doc['doctor_id'], $busyDoctorIds);
                });
            }

            if (empty($availableDoctors)) {
                send_json(['error' => 'All doctors are booked for this time slot. Please choose another time.'], 400);
            }

            // Step 4: Assign the doctor with the fewest appointments that day
            $availableIds2 = array_column($availableDoctors, 'doctor_id');
            $placeholders3 = implode(',', array_fill(0, count($availableIds2), '?'));

            $stmt = $pdo->prepare("
                SELECT a.doctor_id, COUNT(*) as appt_count
                FROM appointments a
                JOIN time_slots s ON a.slot_id = s.slot_id
                WHERE a.doctor_id IN ($placeholders3)
                  AND s.slot_date = ?
                  AND a.status NOT IN ('cancelled')
                GROUP BY a.doctor_id
            ");
            $params3 = $availableIds2;
            $params3[] = $slotDate;
            $stmt->execute($params3);
            $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // If tie or no counts, pick first. Otherwise pick with fewest.
            $bestDoctor = null;
            $lowestCount = PHP_INT_MAX;

            foreach ($availableDoctors as $doc) {
                $count = $counts[$doc['doctor_id']] ?? 0;
                if ($count < $lowestCount) {
                    $lowestCount = $count;
                    $bestDoctor = $doc['doctor_id'];
                }
            }

            if ($bestDoctor) {
                $doctor_id = $bestDoctor;
                error_log("[HAMS] Auto-assigned doctor_id=$doctor_id (had $lowestCount appointments that day)");
            } else {
                // Fallback: first available doctor
                $doctor_id = $availableDoctors[0]['doctor_id'];
            }

        } catch (PDOException $e) {
            error_log('[HAMS] Auto-assign doctor failed: ' . $e->getMessage());

            // Fallback: random active doctor
            try {
                $stmt = $pdo->prepare("
                    SELECT doctor_id FROM doctors
                    WHERE dept_id = :dept_id AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([':dept_id' => $slot['dept_id']]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_id = $doctor ? $doctor['doctor_id'] : null;
            } catch (PDOException $e2) {
                $doctor_id = null;
            }
        }
    }
}

// --- Validate we have a doctor ---
if (!$doctor_id) {
    send_json(['error' => 'Could not assign a doctor. Please contact support.'], 500);
}

// --- Save the appointment using a transaction ---
try {
    $pdo->beginTransaction();

    // 1. Reserve the slot
    $reserve = $pdo->prepare("UPDATE time_slots SET booked_count = 1, is_booked = 1 WHERE slot_id = ? AND is_active = 1 AND is_booked = 0");
    $reserve->execute([$slot_id]);

    if ($reserve->rowCount() === 0) {
        $pdo->rollBack();
        send_json(['error' => 'That slot is no longer available. Please choose another.'], 409);
    }

    // 2. Insert the appointment record
    $stmt = $pdo->prepare("
        INSERT INTO appointments
            (patient_user_id, family_profile_id, dept_id, doctor_id, slot_id, notes, status)
        VALUES
            (:patient, :family, :dept, :doctor, :slot, :notes, 'confirmed')
    ");
    $stmt->execute([
        ':patient' => $uid,
        ':family'  => $family_id ?: null,
        ':dept'    => $dept_id,
        ':doctor'  => $doctor_id,
        ':slot'    => $slot_id,
        ':notes'   => $notes,
    ]);

    $appt_id = $pdo->lastInsertId();

    $pdo->commit();
    send_json(['success' => true, 'appt_id' => $appt_id]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[HAMS] Booking failed: ' . $e->getMessage());
    error_log('[HAMS] Stack trace: ' . $e->getTraceAsString());
    send_json(['error' => 'Booking failed. The slot may have just been taken.'], 500);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[HAMS] General error: ' . $e->getMessage());
    error_log('[HAMS] Stack trace: ' . $e->getTraceAsString());
    send_json(['error' => 'Booking failed. Please try again.'], 500);
}
?>