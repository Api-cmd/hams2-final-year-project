<?php
// Returns the logged-in user's profile data.
require_once 'config.php';
if (!is_logged_in()) send_json(['error' => 'Not logged in.'], 401);

try {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, phone, role, created_at
        FROM users WHERE user_id=?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    send_json($stmt->fetch());
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching profile: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch profile'], 500);
}
?>