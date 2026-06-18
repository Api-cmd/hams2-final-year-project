<?php
require_once 'config.php';
require_role('admin');

try {
    $stmt = $pdo->query("
        SELECT
            a.appt_id,
            a.status,
            s.start_time,
            u_p.full_name AS patient_name,
            u_d.full_name AS doctor_name,
            d.dept_name
        FROM appointments a
        JOIN time_slots  s   ON a.slot_id         = s.slot_id
        JOIN users       u_p ON a.patient_user_id = u_p.user_id
        JOIN doctors     dr  ON a.doctor_id       = dr.doctor_id
        JOIN users       u_d ON dr.user_id        = u_d.user_id
        JOIN departments d   ON a.dept_id         = d.dept_id
        WHERE s.slot_date = CURDATE()
          AND a.status IN ('confirmed', 'arrived', 'seen')
        ORDER BY s.start_time ASC
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching today appointments: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch appointments', 'detail' => $e->getMessage()], 500);
}
?>