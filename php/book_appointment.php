<?php
// ===========================================================
// php/book_appointment.php
// Saves a new appointment to the database.
// CALLED BY: pages/book.html confirm button via fetch()
// METHOD: POST, receives JSON body
// RETURNS: { "success": true } or { "error": "message" }
// ===========================================================

require_once 'config.php';
require_role('patient');

// Read the JSON body sent by the JS fetch() call.
// file_get_contents('php://input') reads the raw POST body.
// json_decode converts the JSON string into a PHP object.
$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    send_json(['error' => 'Invalid request.'], 400);
}

$uid       = $_SESSION['user_id'];
$slot_id   = (int)($body['slot_id']            ?? 0);
$dept_id   = (int)($body['dept_id']            ?? 0);
$doctor_id = $body['doctor_id'] ?? null; // Optional - null means auto-assign
$family_id = (int)($body['family_profile_id']  ?? 0);
$notes     = clean($body['notes']              ?? '');

// Validate all required IDs are present (doctor_id is optional)
if (!$slot_id || !$dept_id) {
    send_json(['error' => 'Missing required fields.'], 400);
}

// --- Verify the slot is still available and not in the past ---
// We check again here even though JS only shows open slots.
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

// Server-side protection: prevent booking past slots
$today = date('Y-m-d');
$nowTime = date('H:i:s');

if (strtotime($slot['slot_date']) < strtotime($today) ||
    ($slot['slot_date'] === $today && $slot['start_time'] <= $nowTime)) {
    send_json(['error' => 'Cannot book a past slot. Please choose another.'], 400);
}

// If the slot already belongs to a doctor, honor that assignment.
if (!empty($slot['doctor_id'])) {
    $doctor_id = $slot['doctor_id'];
} elseif ($doctor_id === null || $doctor_id === '') {
    // Auto-assign doctor if not selected and slot has no pre-assigned doctor.
    try {
        $stmt = $pdo->prepare("
            SELECT doctor_id
            FROM doctors
            WHERE dept_id = :dept_id AND is_active = 1
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute([':dept_id' => $slot['dept_id']]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doctor) {
            $doctor_id = $doctor['doctor_id'];
        } else {
            $doctor_id = null;
        }
    } catch (PDOException $e) {
        error_log('[HAMS] Auto-assign doctor failed: ' . $e->getMessage());
        $doctor_id = null;
    }
} else {
    // Validate that the selected doctor exists, is active, and matches the slot's department.
    $stmt = $pdo->prepare("
        SELECT doctor_id FROM doctors
        WHERE doctor_id = ? AND dept_id = ? AND is_active = 1
    ");
    $stmt->execute([$doctor_id, $slot['dept_id']]);
    if (!$stmt->fetch()) {
        send_json(['error' => 'Selected doctor is not available for this department.'], 400);
    }
}

// --- Save the appointment using a transaction ---
// A transaction groups multiple SQL statements together.
// If ANY statement fails, the entire group is rolled back (undone),
// so we never end up with an appointment saved but the slot not marked booked.
try {
    $pdo->beginTransaction();

    // 1. Reserve the slot by incrementing booked_count in a safe, conditional update.
    $reserve = $pdo->prepare("UPDATE time_slots SET booked_count = 1, is_booked = 1 WHERE slot_id = ? AND is_active = 1 AND is_booked = 0");
    $reserve->execute([$slot_id]);

    if ($reserve->rowCount() === 0) {
        $pdo->rollBack();
        send_json(['error' => 'That slot is no longer available. Please choose another.'], 409);
    }

    // 2. Insert the appointment record with optional doctor_id
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
    // Something went wrong — roll back both changes
    $pdo->rollBack();
    error_log('[HAMS] Booking failed: ' . $e->getMessage());
    send_json(['error' => 'Booking failed. The slot may have just been taken.'], 500);
}
?>