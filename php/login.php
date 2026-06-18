<?php
// ===========================================================
// php/login.php
// Handles the login form submission from index.html.
//
// HOW IT WORKS:
//   1. Receives email + password via POST
//   2. Checks rate limiting for IP address AND specific email
//   3. Looks up the user in the database by email
//   4. Uses password_verify() to check the password hash
//   5. If correct, stores user info in $_SESSION
//   6. Records the login attempt result
//   7. Redirects to the correct dashboard based on role
//
// CALLED BY: index.html form (action="php/login.php")
// METHOD: POST only
// ===========================================================

require_once 'config.php';

// Block anyone who sends a GET request directly to this file.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit();
}

// --- Collect inputs early for rate limiting checks ---
$email    = clean($_POST['email']    ?? '');
$password = clean($_POST['password'] ?? '');

// --- Rate limiting setup ---
$ip = $_SERVER['REMOTE_ADDR'];
$max_attempts_per_ip = 10;      // Maximum failed attempts per IP (any email)
$max_attempts_per_email = 5;     // Maximum failed attempts per specific email
$window = 600;                   // Time window in seconds (10 minutes)

// ===========================================================
// CHECK 1: Rate limiting by IP address (global)
// Prevents one IP from spamming many different emails
// ===========================================================
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM login_attempts
    WHERE ip_address = ?
      AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
      AND success = 0
");
$stmt->execute([$ip, $window]);
$ip_attempts = (int)$stmt->fetchColumn();

if ($ip_attempts >= $max_attempts_per_ip) {
    header('Location: ../index.html?msg=too_many_attempts_ip');
    exit();
}

// ===========================================================
// CHECK 2: Rate limiting by email address
// Prevents brute force attacks targeting a specific account
// Only check if email is provided (not empty)
// ===========================================================
if (!empty($email)) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE email = ?
          AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
          AND success = 0
    ");
    $stmt->execute([$email, $window]);
    $email_attempts = (int)$stmt->fetchColumn();
    
    if ($email_attempts >= $max_attempts_per_email) {
        header('Location: ../index.html?msg=too_many_attempts_email');
        exit();
    }
}

// --- Basic server-side validation ---
if (empty($email) || empty($password)) {
    // Record failed attempt for missing credentials
    $pdo->prepare("
        INSERT INTO login_attempts (ip_address, email, success, attempted_at)
        VALUES (?, ?, 0, NOW())
    ")->execute([$ip, $email]);
    
    header('Location: ../index.html?msg=login_failed');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Record failed attempt for invalid email format
    $pdo->prepare("
        INSERT INTO login_attempts (ip_address, email, success, attempted_at)
        VALUES (?, ?, 0, NOW())
    ")->execute([$ip, $email]);
    
    header('Location: ../index.html?msg=login_failed');
    exit();
}

// --- Look up user in the database ---
$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, password, role
    FROM users
    WHERE email = :email AND is_active = 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// --- Verify the password ---
if (!$user || !password_verify($password, $user['password'])) {
    // Record failed login attempt (includes both IP and email)
    $pdo->prepare("
        INSERT INTO login_attempts (ip_address, email, success, attempted_at)
        VALUES (?, ?, 0, NOW())
    ")->execute([$ip, $email]);
    
    // Check if we need to implement progressive delay after failures
    // This makes brute force slower even within the rate limit window
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE email = ? AND success = 0
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 3600 SECOND)
    ");
    $stmt->execute([$email]);
    $total_failures = (int)$stmt->fetchColumn();
    
    if ($total_failures > 3) {
        // Add progressive delay: 1 second for 4th failure, 2 for 5th, etc.
        $delay = min($total_failures - 3, 5); // Max 5 seconds delay
        sleep($delay);
    }
    
    header('Location: ../index.html?msg=login_failed');
    exit();
}

// --- Login successful: record success ---
$pdo->prepare("
    INSERT INTO login_attempts (ip_address, email, success, attempted_at)
    VALUES (?, ?, 1, NOW())
")->execute([$ip, $email]);

// Optional: Clear old failed attempts for this email after successful login
// This resets the counter for legitimate users
$pdo->prepare("
    DELETE FROM login_attempts 
    WHERE email = ? AND success = 0 
    AND attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
")->execute([$email]);

// Store user data in the session
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['login_ip']   = $ip; // Store for additional security checks

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// --- Redirect based on role ---
switch ($user['role']) {
    case 'patient':
        header('Location: ../pages/dashboard.html');
        break;
    case 'staff':
        header('Location: ../pages/staff-dashboard.html');
        break;
    case 'admin':
        header('Location: ../pages/admin-dashboard.html');
        break;
    default:
        session_destroy();
        header('Location: ../index.html?msg=login_failed');
}
exit();
?>