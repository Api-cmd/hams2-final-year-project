<?php
// Update personal info or change password for the logged-in user.
require_once 'config.php';
if (!is_logged_in()) send_json(['error' => 'Not logged in.'], 401);

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

$uid = $_SESSION['user_id'];

// --- Change password ---
if (!empty($body['change_password'])) {
    $current = $body['current_password'] ?? '';
    $new     = $body['new_password']     ?? '';

    if (strlen($new) < 8) {
        send_json(['error' => 'New password must be at least 8 characters.'], 400);
    }

    // Verify the current password is correct before allowing the change
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
    $stmt->execute([$uid]);
    $row  = $stmt->fetch();

    if (!password_verify($current, $row['password'])) {
        send_json(['error' => 'Current password is incorrect.'], 403);
    }

    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")
        ->execute([$hashed, $uid]);

    send_json(['success' => true]);
}

// --- Update personal info ---
$name  = clean($body['name']  ?? '');
$email = clean($body['email'] ?? '');
$phone = clean($body['phone'] ?? '');

if (!$name || !$email || !$phone) {
    send_json(['error' => 'All fields are required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Invalid email address.'], 400);
}

// Check email not taken by another user
$check = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_id != ?");
$check->execute([$email, $uid]);
if ($check->fetch()) {
    send_json(['error' => 'That email is already in use.'], 409);
}

$pdo->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE user_id=?")
    ->execute([$name, $email, $phone, $uid]);

// Update session name so navbar reflects the change immediately
$_SESSION['user_name']  = $name;
$_SESSION['user_email'] = $email;

send_json(['success' => true]);
?>