<?php
// ===========================================================
// php/get_appointments.php
// Returns a list of appointments for the logged-in patient.
//
// CALLED BY: pages/dashboard.html and pages/appointments.html
// REQUIRES:  Patient must be logged in
//
// OPTIONAL URL PARAMETERS:
//   ?limit=5          — return only the 5 most recent (for dashboard)
//   ?status=upcoming  — filter by upcoming/past/cancelled
//
// RETURNS: JSON array of appointment objects
// ===========================================================

require_once 'config.php';
require_role('patient');

$uid = $_SESSION['user_id'];

// Read optional filter parameters from the URL
// e.g. fetch('../php/get_appointments.php?limit=5')
$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;
$filter = isset($_GET['status']) ? clean($_GET['status']): 'all';

// --- Build the WHERE clause based on the filter ---
// We build the SQL in parts depending on what was requested.
// This avoids writing multiple near-identical queries.
$where = "WHERE a.patient_user_id = :uid";
$params = [':uid' => $uid];

if ($filter === 'upcoming') {
    // Upcoming = not finished and slot date is in the future
    $where .= " AND a.status IN ('pending','confirmed') AND s.slot_date >= CURDATE()";
} elseif ($filter === 'past') {
    $where .= " AND a.status IN ('seen','no_show')";
} elseif ($filter === 'cancelled') {
    $where .= " AND a.status = 'cancelled'";
}
// 'all' filter: no extra condition, return everything

// --- Main query ---
// JOIN pulls in related data from other tables so we don't
// have to make separate queries for doctor name, dept, etc.
$sql = "
    SELECT
        a.appt_id,
        a.status,
        a.notes,
        a.created_at,
        a.cancellation_reason,
        s.slot_date,
        s.start_time,
        s.end_time,
        u.full_name  AS doctor_name,
        d.dept_name,
        fp.full_name AS family_name
    FROM appointments a
    JOIN time_slots       s  ON a.slot_id           = s.slot_id
    JOIN doctors          dr ON a.doctor_id          = dr.doctor_id
    JOIN users            u  ON dr.user_id           = u.user_id
    JOIN departments      d  ON a.dept_id            = d.dept_id
    LEFT JOIN family_profiles fp ON a.family_profile_id = fp.profile_id
    {$where}
    ORDER BY s.slot_date DESC, s.start_time DESC
    LIMIT :limit
";

// Note: LIMIT cannot use a named placeholder in PDO when emulate_prepares is false,
// so we bind it separately using bindValue with an explicit integer type.
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid',   $uid,   PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$appointments = $stmt->fetchAll();

// The data goes straight to JSON — the HTML page builds the table rows from it
send_json($appointments);
?>