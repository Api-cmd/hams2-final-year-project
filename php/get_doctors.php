<?php
// php/get_doctors.php
// Returns active doctors for a specific department
// Called by booking page (patient) to show optional doctor selection
// If patient doesn't choose a doctor, the system will auto-assign one
require_once 'config.php';

// Only patients can call this endpoint
require_role('patient');

// Get department_id from query parameter
$dept_id = $_GET['dept_id'] ?? null;

if (!$dept_id) {
    send_json(['error' => 'Department ID is required.'], 400);
}

try {
    // Query active doctors for the specified department
    // Logic: Only return doctors who are active (is_active = 1)
    // This allows patients to optionally choose their preferred doctor
    $stmt = $pdo->prepare("
        SELECT
            doctor_id,
            full_name,
            specialization
        FROM doctors
        WHERE dept_id = :dept_id
          AND is_active = 1
        ORDER BY full_name ASC
    ");
    $stmt->execute([':dept_id' => $dept_id]);

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching doctors: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch doctors', 'detail' => $e->getMessage()], 500);
}
?>