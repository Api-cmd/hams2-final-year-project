<?php
// Add a new user or edit/toggle an existing one.
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

$user_id = (int)($body['user_id'] ?? 0);

// --- Toggle active/inactive ---
// A simpler operation handled separately from add/edit
if (isset($body['toggle_active'])) {
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([(int)$body['toggle_active'], $user_id]);
    send_json(['success' => true]);
}

// --- Add or edit ---
$name     = clean($body['name']     ?? '');
$email    = clean($body['email']    ?? '');
$phone    = clean($body['phone']    ?? '');
$role     = clean($body['role']     ?? 'patient');
$password = $body['password']       ?? '';

if (!$name || !$email || !$phone) {
    send_json(['error' => 'Name, email, and phone are required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Invalid email address.'], 400);
}

if ($user_id) {
    // --- Editing existing user ---
    if ($password && strlen($password) < 8) {
        send_json(['error' => 'Password must be at least 8 characters.'], 400);
    }

    if ($password) {
        // Update including new password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $pdo->prepare("
            UPDATE users SET full_name=?, email=?, phone=?, role=?, password=?
            WHERE user_id=?
        ");
        $stmt->execute([$name, $email, $phone, $role, $hashed, $user_id]);
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("
            UPDATE users SET full_name=?, email=?, phone=?, role=?
            WHERE user_id=?
        ");
        $stmt->execute([$name, $email, $phone, $role, $user_id]);
    }

} else {
    // --- Adding new user ---
    if (strlen($password) < 8) {
        send_json(['error' => 'Password must be at least 8 characters.'], 400);
    }

    // Check email is not already taken
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        send_json(['error' => 'That email is already registered.'], 409);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $pdo->prepare("
        INSERT INTO users (full_name, email, phone, password, role)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $phone, $hashed, $role]);
}

send_json(['success' => true]);
?>