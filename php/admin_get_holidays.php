<?php
require_once 'config.php';
require_role('admin');

try {
    $stmt = $pdo->query("
        SELECT holiday_id, holiday_date, name, created_at
        FROM holidays
        ORDER BY holiday_date ASC, holiday_id ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching global holidays: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch global holidays', 'detail' => $e->getMessage()], 500);
}
?>
