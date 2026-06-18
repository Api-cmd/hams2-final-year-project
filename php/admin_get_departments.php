<?php
// php/get_departments.php
// Returns all active departments as JSON.
// Called by booking page (patient) AND add doctor modal (admin)
require_once 'config.php';

// Allow both patient and admin to call this endpoint
if (!is_logged_in()) {
    send_json(['error' => 'Not logged in.'], 401);
}

try {
    $stmt = $pdo->query("
        SELECT dept_id, dept_name, description
        FROM departments
        WHERE is_active = 1
        ORDER BY dept_name ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching departments: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch departments', 'detail' => $e->getMessage()], 500);
}
?>