<?php
// ===========================================================
// php/get_departments.php
// Returns all active departments as JSON.
// CALLED BY: pages/book.html on page load
// ===========================================================

require_once 'config.php';

// Log for debugging
error_log('[HAMS] get_departments.php called, session status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive'));
error_log('[HAMS] Session data: ' . json_encode($_SESSION));

require_login();

error_log('[HAMS] User authenticated, fetching departments');

try {
    $stmt = $pdo->query("
        SELECT dept_id, dept_name, description
        FROM departments
        WHERE is_active = 1
        ORDER BY dept_name ASC
    ");

    $departments = $stmt->fetchAll();
    error_log('[HAMS] Found ' . count($departments) . ' departments');
    send_json($departments);
} catch (PDOException $e) {
    error_log('[HAMS] Database error in get_departments: ' . $e->getMessage());
    send_json(['error' => 'Failed to load departments'], 500);
}
?>