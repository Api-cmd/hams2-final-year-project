<?php
require_once 'config.php';
require_role('admin');

try {
    $stmt = $pdo->query("
        SELECT
            dr.doctor_id,
            dr.dept_id,
            dr.specialization,
            dr.bio,
            u.user_id,
            u.full_name,
            u.email,
            u.phone,
            u.is_active,
            d.dept_name,
            COUNT(a.appt_id) AS total_appointments
        FROM doctors dr
        JOIN users       u  ON dr.user_id  = u.user_id
        JOIN departments d  ON dr.dept_id  = d.dept_id
        LEFT JOIN appointments a ON a.doctor_id = dr.doctor_id
        GROUP BY
            dr.doctor_id,
            dr.dept_id,
            dr.specialization,
            dr.bio,
            u.user_id,
            u.full_name,
            u.email,
            u.phone,
            u.is_active,
            d.dept_name
        ORDER BY u.full_name ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctors: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch doctors', 'detail' => $e->getMessage()], 500);
}
?>