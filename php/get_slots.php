<?php
// ===========================================================
// php/get_slots.php
// Returns available (unbooked) time slots for a department on
// a specific date.
// CALLED BY: pages/book.html when a date is chosen
// URL PARAMS: ?dept_id=3&date=2025-06-15
// ===========================================================

require_once 'config.php';
require_role('patient');

$dept_id = (int)($_GET['dept_id'] ?? 0);
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date    = clean($_GET['date']    ?? '');

// Validate date format strictly — must be YYYY-MM-DD
// preg_match checks the string against a pattern:
//   ^ = start, \d{4} = 4 digits, - = dash, \d{2} = 2 digits, $ = end
if (!$dept_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    send_json([]);
}

try {
    // Only return slots that are available, active, and not fully booked.
    // Also exclude past times for today.
    $filters = [
        'ts.dept_id = ?',
        'ts.slot_date = ?',
        'ts.is_active = 1',
        'ts.is_booked = 0',
        '(ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.start_time > CURTIME()))',
    ];
    $params = [$dept_id, $date];

    if ($doctor_id > 0) {
        $filters[] = 'ts.doctor_id = ?';
        $params[] = $doctor_id;
    }

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

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch time slots'], 500);
}
?>