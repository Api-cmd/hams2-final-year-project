<?php
// php/admin_delete_doctor.php
// Deletes a doctor profile
// Called by admin-doctors.html page
require_once 'config.php';

// Only admin can access this endpoint
require_role('admin');

// Get JSON input from request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$doctor_id = $input['doctor_id'] ?? null;

if (!$doctor_id) {
    send_json(['error' => 'Doctor ID is required.'], 400);
}

try {
    $pdo->beginTransaction();

    // Check if doctor has any appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        $pdo->rollBack();
        send_json(['error' => 'Cannot delete doctor with existing appointments. Disable the doctor instead.'], 400);
    }

    // Delete the doctor
    $stmt = $pdo->prepare("DELETE FROM doctors WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        send_json(['error' => 'Doctor not found.'], 404);
    }

    $pdo->commit();
    send_json(['success' => true, 'message' => 'Doctor deleted successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[HAMS] Error deleting doctor: ' . $e->getMessage());
    send_json(['error' => 'Failed to delete doctor', 'detail' => $e->getMessage()], 500);
}
?>
