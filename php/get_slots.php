<?php
// ===========================================================
// php/get_slots.php
// Returns available (unbooked) time slots for a doctor on
// a specific date.
// CALLED BY: pages/book.html when a date is chosen
// URL PARAMS: ?doctor_id=3&date=2025-06-15
// ===========================================================

require_once 'config.php';
require_role('patient');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date      = clean($_GET['date']      ?? '');

// Validate date format strictly — must be YYYY-MM-DD
// preg_match checks the string against a pattern:
//   ^ = start, \d{4} = 4 digits, - = dash, \d{2} = 2 digits, $ = end
if (!$doctor_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    send_json([]);
}

try {
    // Only return slots that are available (is_booked = 0) and active
    $stmt = $pdo->prepare("
        SELECT slot_id, start_time, end_time
        FROM time_slots
        WHERE doctor_id = ?
          AND slot_date  = ?
          AND is_booked  = 0
          AND is_active  = 1
        ORDER BY start_time ASC
    ");
    $stmt->execute([$doctor_id, $date]);

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch time slots'], 500);
}
?>