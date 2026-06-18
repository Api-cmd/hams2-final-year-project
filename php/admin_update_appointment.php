<?php
// Updates the status of any appointment. Admin only.
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);

$appt_id = (int)($body['appt_id'] ?? 0);
$status  = clean($body['status']  ?? '');

// Only allow valid status transitions
$allowed = ['pending','confirmed','arrived','seen','cancelled','no_show'];

if (!$appt_id || !in_array($status, $allowed)) {
    send_json(['error' => 'Invalid input.'], 400);
}

$stmt = $pdo->prepare("UPDATE appointments SET status=? WHERE appt_id=?");
$stmt->execute([$status, $appt_id]);

send_json(['success' => true]);
?>