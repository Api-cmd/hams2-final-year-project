<?php
// Add, edit, or delete a family profile for the logged-in patient.
require_once 'config.php';
require_role('patient');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

$uid        = $_SESSION['user_id'];
$profile_id = (int)($body['profile_id'] ?? 0);

// --- Delete ---
if (!empty($body['delete'])) {
    // Verify the profile belongs to this patient before deleting
    $stmt = $pdo->prepare("
        DELETE FROM family_profiles
        WHERE profile_id=? AND patient_user_id=?
    ");
    $stmt->execute([$profile_id, $uid]);
    send_json(['success' => true]);
}

// --- Add or Edit ---
$name     = clean($body['name']          ?? '');
$relation = clean($body['relationship']  ?? '');
$dob      = clean($body['date_of_birth'] ?? '');

if (!$name || !$relation) {
    send_json(['error' => 'Name and relationship are required.'], 400);
}

// Convert empty string to null for the DATE column
$dob = $dob ?: null;

if ($profile_id) {
    // Edit — verify ownership first
    $stmt = $pdo->prepare("
        UPDATE family_profiles
        SET full_name=?, relationship=?, date_of_birth=?
        WHERE profile_id=? AND patient_user_id=?
    ");
    $stmt->execute([$name, $relation, $dob, $profile_id, $uid]);
} else {
    // Add new
    $stmt = $pdo->prepare("
        INSERT INTO family_profiles (patient_user_id, full_name, relationship, date_of_birth)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$uid, $name, $relation, $dob]);
}

send_json(['success' => true]);
?>