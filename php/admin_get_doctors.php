<?php
// php/admin_get_doctors.php
// Returns all doctors with their department information
// Called by admin-doctors.html page
require_once 'config.php';

// Only admin can access this endpoint
require_role('admin');

try {
    // Query all doctors with their department names
    // LEFT JOIN ensures we get all doctors even if department is inactive
    $stmt = $pdo->query("
        SELECT
            d.doctor_id,
            d.dept_id,
            d.full_name,
            d.specialization,
            d.bio,
            d.is_active,
            d.created_at,
            dept.dept_name
        FROM doctors d
        LEFT JOIN departments dept ON d.dept_id = dept.dept_id
        ORDER BY d.dept_id ASC, d.full_name ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctors: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch doctors', 'detail' => $e->getMessage()], 500);
}
?>
