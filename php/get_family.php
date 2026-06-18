<?php
// php/get_family.php
// Returns family profiles for the logged-in patient.
// FIXED: added date_of_birth to the SELECT statement
require_once 'config.php';
require_role('patient');

try {
    $stmt = $pdo->prepare("
        SELECT profile_id, full_name, relationship, date_of_birth
        FROM family_profiles
        WHERE patient_user_id = ?
        ORDER BY full_name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);

    send_json($stmt->fetchAll());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching family profiles: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch family profiles'], 500);
}
?>