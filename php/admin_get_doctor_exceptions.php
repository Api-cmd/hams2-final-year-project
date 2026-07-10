<?php
require_once 'config.php';
require_role('admin');

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($doctor_id <= 0) {
    send_json([]);
}

try {
    $stmt = $pdo->prepare("SELECT exception_id, exception_date, is_working, start_time, end_time, break_start, break_end, note
        FROM schedule_exceptions
        WHERE doctor_id = ?
        ORDER BY exception_date ASC");
    $stmt->execute([$doctor_id]);
    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctor exceptions: ' . $e->getMessage());
    send_json(['error' => 'Failed to load doctor exceptions', 'detail' => $e->getMessage()], 500);
}
