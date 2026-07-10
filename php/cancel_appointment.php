<?php
// Cancels an appointment belonging to the logged-in patient.
// Frees the time slot so another patient can book it.
require_once 'config.php';
require_role('patient');

$body    = json_decode(file_get_contents('php://input'), true);
$appt_id = (int)($body['appt_id'] ?? 0);

if (!$appt_id) send_json(['error' => 'Invalid request.'], 400);

// Verify this appointment actually belongs to the logged-in patient.
// Without this check, a patient could cancel someone else's appointment
// by guessing their appt_id.
$stmt = $pdo->prepare("
    SELECT appt_id, slot_id, status FROM appointments
    WHERE appt_id = ? AND patient_user_id = ?
");
$stmt->execute([$appt_id, $_SESSION['user_id']]);
$appt = $stmt->fetch();

if (!$appt) {
    send_json(['error' => 'Appointment not found.'], 404);
}

// Only pending or confirmed appointments can be cancelled
if (!in_array($appt['status'], ['pending', 'confirmed'])) {
    send_json(['error' => 'This appointment cannot be cancelled.'], 400);
}

// Use a transaction: cancel the appointment AND update the slot together.
// If either fails, both are rolled back.
try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE appt_id=?")
        ->execute([$appt_id]);

    // Reset slot to available for single-occupancy model
    $pdo->prepare(
        "UPDATE time_slots
         SET booked_count = 0,
             is_booked = 0
         WHERE slot_id = ?"
    )->execute([$appt['slot_id']]);

    $pdo->commit();
    send_json(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[HAMS] Cancel error: ' . $e->getMessage());
    send_json(['error' => 'Cancellation failed. Try again.'], 500);
}
?>