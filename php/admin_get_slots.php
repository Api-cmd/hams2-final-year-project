<?php
// php/admin_get_slots.php
// Returns all time slots for the admin slots page.
// Includes doctor name, department and slot status so the
// admin page can render booked/active state correctly.
// UPDATED: Automatically excludes expired slots (past date/time)
require_once 'config.php';
require_role('admin');

try {
    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
    $date      = clean($_GET['date'] ?? '');

    $whereClauses = [];
    $params = [];

    if ($doctor_id) {
        $whereClauses[] = 'ts.doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctor_id;
    }

    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $whereClauses[] = 'ts.slot_date = :date';
        $params[':date'] = $date;
    }

    // Auto-exclude expired slots (date has passed or time has passed on today)
    $whereClauses[] = '(ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.end_time > CURTIME()))';

    $whereSql = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $sql = "
        SELECT
            ts.slot_id,
            ts.slot_date,
            ts.start_time,
            ts.end_time,
            ts.is_booked,
            ts.is_active,
            u.full_name AS doctor_name,
            d.dept_name
        FROM time_slots ts
        JOIN doctors dr ON ts.doctor_id = dr.doctor_id
        JOIN users u ON dr.user_id = u.user_id
        JOIN departments d ON dr.dept_id = d.dept_id
        $whereSql
        ORDER BY ts.slot_date DESC, ts.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching slots: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch slots', 'detail' => $e->getMessage()], 500);
}
?>