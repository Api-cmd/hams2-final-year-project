<?php
// ===========================================================
// php/get_doctors.php
// Returns doctors for a given department as JSON.
// CALLED BY: pages/book.html when department is selected
// URL PARAM: ?dept_id=2
// ===========================================================

require_once 'config.php';
require_role('patient');

$dept_id = (int)($_GET['dept_id'] ?? 0);

if (!$dept_id) {
    send_json([]); // Return empty array for missing input
}

try {
    // JOIN doctors + users to get the doctor's name alongside
    // their specialization from the doctors table
    $stmt = $pdo->prepare("
        SELECT dr.doctor_id, u.full_name, dr.specialization
        FROM doctors dr
        JOIN users u ON dr.user_id = u.user_id
        WHERE dr.dept_id = ? AND u.is_active = 1
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$dept_id]);

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctors: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch doctors', 'detail' => $e->getMessage()], 500);
}
?>