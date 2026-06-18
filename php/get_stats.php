<?php
// ===========================================================
// php/get_stats.php
// Returns appointment counts for the patient dashboard cards.
//
// CALLED BY: pages/dashboard.html via fetch()
// REQUIRES:  Patient must be logged in
// RETURNS JSON:
//   { "total": 5, "upcoming": 2, "completed": 2, "cancelled": 1 }
// ===========================================================

require_once 'config.php';

// Stop immediately if the caller is not a logged-in patient
require_role('patient');

$uid = $_SESSION['user_id'];

try {
    // --- Count total appointments ever booked by this patient ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM appointments
        WHERE patient_user_id = ?
    ");
    $stmt->execute([$uid]);
    $total = (int)$stmt->fetchColumn();

    // --- Count upcoming: pending or confirmed, slot date is today or future ---
    // We JOIN time_slots to access the slot_date column for the date comparison.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS upcoming
        FROM appointments a
        JOIN time_slots s ON a.slot_id = s.slot_id
        WHERE a.patient_user_id = ?
          AND a.status IN ('pending', 'confirmed')
          AND s.slot_date >= CURDATE()
    ");
    $stmt->execute([$uid]);
    $upcoming = (int)$stmt->fetchColumn();

    // --- Count completed: appointments where the doctor marked 'seen' ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE patient_user_id = ? AND status = 'seen'
    ");
    $stmt->execute([$uid]);
    $completed = (int)$stmt->fetchColumn();

    // --- Count cancelled ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE patient_user_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$uid]);
    $cancelled = (int)$stmt->fetchColumn();

    // Send all four numbers as one JSON object
    send_json([
        'total'     => $total,
        'upcoming'  => $upcoming,
        'completed' => $completed,
        'cancelled' => $cancelled,
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching patient stats: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch stats', 'detail' => $e->getMessage()], 500);
}
?>