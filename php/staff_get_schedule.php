<?php
// Returns today's appointments for the logged-in doctor only.
// Staff cannot see other doctors' patients.
require_once 'config.php';
require_role('staff');

// Get this staff member's doctor_id from the doctors table
$stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    // Staff account exists but has no doctor profile yet
    send_json(['error' => 'No doctor profile linked to this account.'], 404);
}

$stmt = $pdo->prepare("
    SELECT
        a.appt_id,
        a.status,
        a.notes,
        s.start_time,
        s.end_time,
        u.full_name   AS patient_name,
        u.phone       AS patient_phone,
        fp.full_name  AS family_name
    FROM appointments a
    JOIN time_slots       s  ON a.slot_id           = s.slot_id
    JOIN users            u  ON a.patient_user_id   = u.user_id
    LEFT JOIN family_profiles fp ON a.family_profile_id = fp.profile_id
    WHERE a.doctor_id  = ?
      AND s.slot_date  = CURDATE()
      AND a.status NOT IN ('cancelled', 'no_show')
    ORDER BY s.start_time ASC
");
$stmt->execute([$doctor['doctor_id']]);

send_json($stmt->fetchAll());
?>