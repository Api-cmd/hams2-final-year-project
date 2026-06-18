<?php
// Add a new doctor profile or edit an existing one.
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

$doctor_id = (int)($body['doctor_id']     ?? 0);
$user_id   = (int)($body['user_id']       ?? 0);
$dept_id   = (int)($body['dept_id']       ?? 0);
$spec      = clean($body['specialization']?? '');
$bio       = clean($body['bio']           ?? '');

if (!$dept_id) send_json(['error' => 'Department is required.'], 400);

if ($doctor_id) {
    // Edit existing doctor
    $pdo->prepare("
        UPDATE doctors SET dept_id=?, specialization=?, bio=?
        WHERE doctor_id=?
    ")->execute([$dept_id, $spec, $bio, $doctor_id]);
} else {
    // Add new doctor — user_id required
    if (!$user_id) send_json(['error' => 'Staff account is required.'], 400);

    // Check this user is not already a doctor
    $check = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id=?");
    $check->execute([$user_id]);
    if ($check->fetch()) {
        send_json(['error' => 'This staff member already has a doctor profile.'], 409);
    }

    $pdo->prepare("
        INSERT INTO doctors (user_id, dept_id, specialization, bio)
        VALUES (?, ?, ?, ?)
    ")->execute([$user_id, $dept_id, $spec, $bio]);
}

send_json(['success' => true]);
?>