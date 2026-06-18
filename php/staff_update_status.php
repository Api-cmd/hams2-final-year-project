<?php
require_once 'config.php';
require_role('staff');

$body    = json_decode(file_get_contents('php://input'), true);
$appt_id = (int)($body['appt_id'] ?? 0);
$status  = clean($body['status']  ?? '');

// Added no_show — doctor marks this at end of day for patients
// who had a confirmed slot but never arrived
$allowed = ['arrived', 'seen', 'no_show'];

if (!$appt_id || !in_array($status, $allowed)) {
    error_log("[HAMS] staff_update_status: invalid input by user {$_SESSION['user_id']} - appt:{$appt_id} status:{$status}");
    send_json(['error' => 'Invalid input.'], 400);
}

// Verify appointment belongs to this doctor
$stmt = $pdo->prepare("
    SELECT a.appt_id, a.doctor_id FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.appt_id = ? AND d.user_id = ?
");
$stmt->execute([$appt_id, $_SESSION['user_id']]);
$found = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$found) {
    error_log("[HAMS] staff_update_status: access denied for user {$_SESSION['user_id']} - appt:{$appt_id}");
    send_json(['error' => 'Appointment not found or access denied.'], 403);
}

try {
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appt_id = ?");
    $stmt->execute([$status, $appt_id]);
    error_log("[HAMS] staff_update_status: user {$_SESSION['user_id']} set appt {$appt_id} status to {$status}");
    send_json(['success' => true]);
} catch (PDOException $e) {
    error_log('[HAMS] staff_update_status failed: ' . $e->getMessage());
    send_json(['error' => 'Update failed.'], 500);
}
?>