<?php
// ===========================================================
// php/staff_get_full_schedule.php
// Returns ALL appointments for the logged-in doctor across
// all dates — past, today, and future.
//
// Unlike staff_get_schedule.php which only returns today,
// this returns everything so the schedule page can filter
// client-side without extra server requests.
// ===========================================================

require_once 'config.php';
require_role('staff');

// Get this doctor's doctor_id
$stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    send_json(['error' => 'No doctor profile linked to this account.'], 404);
}

$stmt = $pdo->prepare("
    SELECT
        a.appt_id,
        a.status,
        a.notes,
        a.created_at,
        s.slot_date,
        s.start_time,
        s.end_time,
        u.full_name   AS patient_name,
        u.phone       AS patient_phone,
        fp.full_name  AS family_name
    FROM appointments a
    JOIN time_slots        s  ON a.slot_id            = s.slot_id
    JOIN users             u  ON a.patient_user_id    = u.user_id
    LEFT JOIN family_profiles fp ON a.family_profile_id = fp.profile_id
    WHERE a.doctor_id = ?
    ORDER BY s.slot_date ASC, s.start_time ASC
");
$stmt->execute([$doctor['doctor_id']]);

send_json($stmt->fetchAll());
?>  