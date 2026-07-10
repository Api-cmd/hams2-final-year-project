<?php
// php/admin_get_slots.php
// Returns all time slots for the admin slots page.
// HAMS2: Slots are department-based, not doctor-specific
// Includes department name and slot status so the
// admin page can render booked/active state correctly.
// UPDATED: Automatically excludes expired slots (past date/time)
require_once 'config.php';
require_role('admin');

try {
    $dept_id = (int)($_GET['dept_id'] ?? 0);
    $date    = clean($_GET['date'] ?? '');

    $whereClauses = [];
    $params = [];

    // Only apply dept_id filter if it's not 0 (empty selection)
    if ($dept_id > 0) {
        $whereClauses[] = 'ts.dept_id = :dept_id';
        $params[':dept_id'] = $dept_id;
    }

    // Only apply date filter if it's provided and valid
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $whereClauses[] = 'ts.slot_date = :date';
        $params[':date'] = $date;
    }

    // Auto-exclude expired slots (date has passed or time has passed on today)
    $whereClauses[] = '(ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.end_time > CURTIME()))';

    if (isset($_GET['doctor_id']) && (int)$_GET['doctor_id'] > 0) {
        $whereClauses[] = 'ts.doctor_id = :doctor_id';
        $params[':doctor_id'] = (int)$_GET['doctor_id'];
    }

    $whereSql = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $sql = "
        SELECT
            ts.slot_id,
            ts.slot_date,
            ts.start_time,
            ts.end_time,
            ts.capacity,
            ts.booked_count,
            ts.is_booked,
            ts.is_active,
            ts.doctor_id,
            d.dept_name,
            doc.full_name AS doctor_name,
            GREATEST(ts.capacity - ts.booked_count, 0) AS available_count
        FROM time_slots ts
        JOIN departments d ON ts.dept_id = d.dept_id
        LEFT JOIN doctors doc ON ts.doctor_id = doc.doctor_id
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