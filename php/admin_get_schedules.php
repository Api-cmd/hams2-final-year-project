<?php
// Returns all doctors with their 7-day schedule rules.
// Used by admin-schedules.html to build the weekly grid cards.
require_once 'config.php';
require_role('admin');

try {
    // Get all active doctors
    $doctors = $pdo->query("
        SELECT dr.doctor_id, u.full_name, dr.specialization, d.dept_name
        FROM doctors     dr
        JOIN users       u  ON dr.user_id = u.user_id
        JOIN departments d  ON dr.dept_id = d.dept_id
        WHERE u.is_active = 1
        ORDER BY u.full_name ASC
    ")->fetchAll();

    foreach ($doctors as &$doctor) {
        // For each doctor, load all 7 days of their schedule
        $stmt = $pdo->prepare("
            SELECT day_of_week, is_working, start_time, end_time, slot_duration
            FROM doctor_schedules
            WHERE doctor_id = ?
            ORDER BY day_of_week ASC
        ");
        $stmt->execute([$doctor['doctor_id']]);
        $rows = $stmt->fetchAll();

        // If a doctor has no schedule rows yet, create defaults in memory
        // so the page still renders correctly
        $scheduleByDay = [];
        foreach ($rows as $row) {
            $scheduleByDay[$row['day_of_week']] = $row;
        }

        $doctor['schedule'] = [];
        for ($d = 0; $d <= 6; $d++) {
            $doctor['schedule'][] = $scheduleByDay[$d] ?? [
                'day_of_week'   => $d,
                'is_working'    => ($d >= 1 && $d <= 5) ? 1 : 0, // Mon-Fri default
                'start_time'    => '08:00:00',
                'end_time'      => $d === 6 ? '14:00:00' : '17:00:00',
                'slot_duration' => 20,
            ];
        }
    }

    send_json($doctors);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctor schedules: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch doctor schedules', 'detail' => $e->getMessage()], 500);
}
?>