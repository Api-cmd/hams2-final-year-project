<?php
require_once 'config.php';
require_once 'cache.php';
require_role('admin');

try {
    // Try to get from cache first
    $cached = cache_get('admin_stats');
    if ($cached) {
        send_json($cached); // Return cached result, no DB query
    }

    // Cache miss — query the database
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
    $patients = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE is_active = 1");
    $departments = $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COUNT(*) FROM appointments a
        JOIN time_slots s ON a.slot_id = s.slot_id
        WHERE s.slot_date = CURDATE()
    ");
    $today = $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COUNT(*) FROM appointments
        WHERE status = 'pending'
    ");
    $pending = $stmt->fetchColumn();

    $stats = [
        'patients' => (int)$patients,
        'departments' => (int)$departments,
        'today'    => (int)$today,
        'pending'  => (int)$pending,
    ];

    // Store in cache for 5 minutes
    cache_set('admin_stats', $stats, 300);

    send_json($stats);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching admin stats: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch stats', 'detail' => $e->getMessage()], 500);
}
?>