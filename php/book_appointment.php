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
$doctor_id = (int)($body['doctor_id']          ?? 0);
$dept_id   = (int)($body['dept_id']            ?? 0);
$family_id = (int)($body['family_profile_id']  ?? 0);
$notes     = clean($body['notes']              ?? '');

// Validate all required IDs are present
if (!$slot_id || !$doctor_id || !$dept_id) {
    send_json(['error' => 'Missing required fields.'], 400);
}

// --- Verify the slot is still available and not in the past ---
// We check again here even though JS only shows unbooked slots,
// because two patients could click at the same time.
$stmt = $pdo->prepare("
    SELECT slot_id, slot_date, start_time FROM time_slots
    WHERE slot_id = ? AND is_booked = 0 AND is_active = 1
");
$stmt->execute([$slot_id]);
$slot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slot) {
    send_json(['error' => 'That slot is no longer available. Please choose another.'], 409);
}

// Server-side protection: prevent booking past slots
$today = date('Y-m-d');
$nowTime = date('H:i:s');

if (strtotime($slot['slot_date']) < strtotime($today) ||
    ($slot['slot_date'] === $today && $slot['start_time'] <= $nowTime)) {
    send_json(['error' => 'Cannot book a past slot. Please choose another.'], 400);
}

// --- Save the appointment using a transaction ---
// A transaction groups multiple SQL statements together.
// If ANY statement fails, the entire group is rolled back (undone),
// so we never end up with an appointment saved but the slot not marked booked.
try {
    $pdo->beginTransaction();

    // 1. Insert the appointment record
    $stmt = $pdo->prepare("
        INSERT INTO appointments
            (patient_user_id, family_profile_id, doctor_id, slot_id, dept_id, notes, status)
        VALUES
            (:patient, :family, :doctor, :slot, :dept, :notes, 'confirmed')
    ");
    $stmt->execute([
        ':patient' => $uid,
        ':family'  => $family_id ?: null, // null means the appointment is for the patient themselves
        ':doctor'  => $doctor_id,
        ':slot'    => $slot_id,
        ':dept'    => $dept_id,
        ':notes'   => $notes,
    ]);

    $appt_id = $pdo->lastInsertId(); // Get the ID of the row we just created

    // 2. Mark the slot as booked so no one else can take it
    $pdo->prepare("UPDATE time_slots SET is_booked = 1 WHERE slot_id = ?")
        ->execute([$slot_id]);

    // Commit means "save all of this permanently"
    $pdo->commit();

    send_json(['success' => true, 'appt_id' => $appt_id]);

} catch (PDOException $e) {
    // Something went wrong — roll back both changes
    $pdo->rollBack();
    error_log('[HAMS] Booking failed: ' . $e->getMessage());
    send_json(['error' => 'Booking failed. The slot may have just been taken.'], 500);
}
?>