<?php
// Returns staff users who do NOT yet have a doctor profile.
// Used by the Add Doctor modal to populate the staff dropdown.
require_once 'config.php';
require_role('admin');

try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.full_name, u.email
        FROM users u
        WHERE u.role = 'staff'
          AND u.is_active = 1
          AND u.user_id NOT IN (
              SELECT user_id FROM doctors
          )
        ORDER BY u.full_name ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching staff users: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch staff users', 'detail' => $e->getMessage()], 500);
}
?>