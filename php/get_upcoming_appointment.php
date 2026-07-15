<?php
// ===========================================================
// php/get_upcoming_appointment.php
// Returns the patient's next upcoming appointment only.
//
// CALLED BY: pages/dashboard.html (new hero card)
// REQUIRES:  Patient must be logged in
//
// RETURNS: JSON object with upcoming appointment data,
//          or null if no upcoming appointment exists.
// ===========================================================

require_once 'config.php';
require_role('patient');

$uid = $_SESSION['user_id'];

$sql = "
    SELECT
        a.appt_id,
        a.status,
        s.slot_date,
        s.start_time,
        s.end_time,
        COALESCE(dr.full_name, sdr.full_name) AS doctor_name,
        d.dept_name,
        fp.full_name AS family_name
    FROM appointments a
    JOIN time_slots       s   ON a.slot_id            = s.slot_id
    LEFT JOIN doctors     dr  ON a.doctor_id          = dr.doctor_id
    LEFT JOIN doctors     sdr ON s.doctor_id          = sdr.doctor_id
    JOIN departments      d   ON a.dept_id            = d.dept_id
    LEFT JOIN family_profiles fp ON a.family_profile_id = fp.profile_id
    WHERE a.patient_user_id = :uid
      AND a.status IN ('pending', 'confirmed')
      AND s.slot_date >= CURDATE()
    ORDER BY s.slot_date ASC, s.start_time ASC
    LIMIT 1
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    $stmt->execute();
    
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appt) {
        send_json($appt);
    } else {
        send_json(null);
    }
} catch (PDOException $e) {
    error_log('[HAMS] get_upcoming_appointment.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to load upcoming appointment.'], 500);
}