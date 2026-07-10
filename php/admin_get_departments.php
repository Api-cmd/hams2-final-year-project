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
    // Query departments with count of active doctors for each department
    // LEFT JOIN ensures we still show departments even if they have no doctors
    // COUNT only counts non-NULL doctor_id values
    // DISTINCT ensures we don't get duplicate departments
    $stmt = $pdo->query("
        SELECT DISTINCT
            d.dept_id,
            d.dept_name,
            d.description,
            d.is_active,
            (SELECT COUNT(doc.doctor_id) FROM doctors doc WHERE doc.dept_id = d.dept_id AND doc.is_active = 1) AS doctor_count
        FROM departments d
        WHERE d.is_active = 1
        ORDER BY d.dept_name ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching departments: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch departments', 'detail' => $e->getMessage()], 500);
}
?>