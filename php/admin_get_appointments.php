<?php
// Returns all appointments system-wide for the admin view.
// HAMS2: Doctors don't have user accounts, so we directly use doctors.full_name
// doctor_id can be NULL (auto-assign case), so we use LEFT JOIN
require_once 'config.php';
require_role('admin');

try {
    $stmt = $pdo->query("
        SELECT
            a.appt_id,
            a.status,
            a.notes,
            a.created_at,
            s.slot_date,
            s.start_time,
            s.end_time,
            u_p.full_name AS patient_name,
            dr.full_name AS doctor_name,
            d.dept_name,
            CASE 
                WHEN a.status IN ('pending', 'confirmed') AND s.slot_date >= CURDATE() THEN 1
                WHEN a.status IN ('pending', 'confirmed') THEN 2
                WHEN a.status IN ('seen', 'no_show') THEN 3
                WHEN a.status = 'cancelled' THEN 4
                ELSE 5
            END AS status_priority
        FROM appointments a
        JOIN time_slots  s   ON a.slot_id          = s.slot_id
        JOIN users       u_p ON a.patient_user_id  = u_p.user_id
        LEFT JOIN doctors dr  ON a.doctor_id       = dr.doctor_id
        JOIN departments d   ON a.dept_id          = d.dept_id
        ORDER BY status_priority ASC, s.slot_date ASC, s.start_time ASC
        LIMIT 500
    ");

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching appointments: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch appointments'], 500);
}
?>