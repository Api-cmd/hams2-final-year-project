<?php
// Saves the weekly schedule rules for one doctor.
// Uses INSERT ... ON DUPLICATE KEY UPDATE so it works
// for both first-time setup and editing existing rules.
require_once 'config.php';
require_role('admin');

$body      = json_decode(file_get_contents('php://input'), true);
$doctor_id = (int)($body['doctor_id'] ?? 0);
$days      = $body['days'] ?? [];

if (!$doctor_id || empty($days)) {
    send_json(['error' => 'Invalid request.'], 400);
}

$stmt = $pdo->prepare("
    INSERT INTO doctor_schedules
        (doctor_id, day_of_week, is_working, start_time, end_time, slot_duration)
    VALUES
        (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        is_working    = VALUES(is_working),
        start_time    = VALUES(start_time),
        end_time      = VALUES(end_time),
        slot_duration = VALUES(slot_duration)
");
// ON DUPLICATE KEY UPDATE:
// If a row already exists for this doctor+day, update it.
// If it doesn't exist, insert it.
// This means one query handles both add and edit.

foreach ($days as $day) {
    $stmt->execute([
        $doctor_id,
        (int)$day['day_of_week'],
        (int)$day['is_working'],
        $day['start_time'] . ':00',  // ensure HH:MM:SS format
        $day['end_time']   . ':00',
        (int)$day['slot_duration'],
    ]);
}

send_json(['success' => true]);
?>