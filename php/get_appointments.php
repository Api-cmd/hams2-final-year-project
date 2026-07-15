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

// Debug: Log the user ID to check if session is working
error_log('[HAMS] get_appointments.php - User ID: ' . $uid);

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
// HAMS2: Doctors don't have user accounts, so we directly use doctors.full_name
// doctor_id can be NULL (auto-assign case), so we use LEFT JOIN
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
        dr.full_name AS doctor_name,
        d.dept_name,
        fp.full_name AS family_name
    FROM appointments a
    JOIN time_slots       s  ON a.slot_id           = s.slot_id
    LEFT JOIN doctors     dr ON a.doctor_id         = dr.doctor_id
    JOIN departments      d  ON a.dept_id            = d.dept_id
    LEFT JOIN family_profiles fp ON a.family_profile_id = fp.profile_id
    {$where}
    ORDER BY s.slot_date DESC, s.start_time DESC
    LIMIT :limit
";

error_log('[HAMS] get_appointments.php - SQL: ' . $sql);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':uid',   $uid,   PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $appointments = $stmt->fetchAll();
    error_log('[HAMS] get_appointments.php - Found ' . count($appointments) . ' appointments');

    send_json($appointments);
} catch (PDOException $e) {
    error_log('[HAMS] get_appointments.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to load appointments. Please contact support.'], 500);
}