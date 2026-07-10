<?php


// ===========================================================
// php/config.php
// Central configuration file for HAMS (Hospital Appointment Management System)
// This file is required in almost every PHP script.
// ===========================================================

// ====================== DATABASE CREDENTIALS ======================
// Update these values according to your environment (XAMPP, live server, etc.)
define('DB_HOST', 'localhost');
define('DB_NAME', 'hams2_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ====================== APPLICATION SETTINGS ======================
define('APP_NAME', 'HAMS2');
define('APP_URL',  'http://localhost/hams2');

// Set timezone to Tanzania (East Africa Time - EAT)
date_default_timezone_set('Africa/Dar_es_Salaam');

// ====================== SESSION CONFIGURATION ======================
// Security and session lifetime settings
ini_set('session.gc_maxlifetime',  7200);     // 2 hours
ini_set('session.cookie_lifetime', 7200);      // 2 hours
ini_set('session.cookie_httponly', 1);         // Prevent JavaScript access to session cookie
ini_set('session.use_strict_mode', 1);         // Prevent session fixation
ini_set('session.cookie_path', '/');           // Make session available across all subdirectories

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====================== DATABASE CONNECTION ======================
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements (more secure)
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('[HAMS] Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please contact administrator.']);
    exit();
}

// ===========================================================
// HELPER FUNCTIONS
// ===========================================================

/**
 * Send JSON response and terminate script execution.
 * Used for all API-style responses in the system.
 *
 * @param mixed $data        Data to encode as JSON
 * @param int   $status_code HTTP status code
 */
function send_json($data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Check if user is currently logged in.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in (basic check).
 * Sends 401 if not logged in.
 */
function require_login(): void {
    if (!is_logged_in()) {
        send_json(['error' => 'Not logged in'], 401);
    }
}

/**
 * Require user to be logged in AND have a specific role.
 *
 * @param string $role  'patient' or 'admin'
 */
function require_role(string $role): void {
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        send_json(['error' => 'Access denied'], 403);
    }
}

/**
 * Sanitize user input by trimming whitespace and removing HTML tags.
 * Use this on all $_POST and $_GET string inputs.
 *
 * @param string $value
 * @return string
 */
function clean(string $value): string {
    return strip_tags(trim($value));
}

function is_valid_time(string $value): bool {
    return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value) === 1;
}

function time_to_minutes(string $value): int {
    [$h, $m] = explode(':', $value);
    return (int)$h * 60 + (int)$m;
}

function minutes_to_time(int $minutes): string {
    $hours = intdiv($minutes, 60);
    $mins  = $minutes % 60;
    return sprintf('%02d:%02d:00', $hours, $mins);
}

// ===========================================================
// RATE LIMITING & SECURITY FUNCTIONS
// ===========================================================

/**
 * Check login rate limits to prevent brute force and credential stuffing attacks.
 * Checks both IP address and email address.
 *
 * @param string|null $email Email to check (optional but recommended)
 */
function check_login_rate_limit(?string $email = null): void {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];

    // --- IP-based rate limiting (prevents attacks from same IP) ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? 
        AND success = 0
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 600 SECOND)
    ");
    $stmt->execute([$ip]);
    $ip_attempts = (int)$stmt->fetchColumn();

    if ($ip_attempts >= 10) {
        send_json([
            'error' => 'Too many login attempts from this IP. Please try again in 10 minutes.'
        ], 429);
    }

    // --- Email-based rate limiting (protects individual accounts) ---
    if ($email && !empty($email)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE email = ? 
            AND success = 0
            AND attempted_at > DATE_SUB(NOW(), INTERVAL 600 SECOND)
        ");
        $stmt->execute([$email]);
        $email_attempts = (int)$stmt->fetchColumn();

        if ($email_attempts >= 5) {
            send_json([
                'error' => 'Too many failed attempts for this account. Please try again in 10 minutes.'
            ], 429);
        }

        // Progressive delay to slow down brute force tools
        if ($email_attempts >= 3) {
            $delay = min($email_attempts - 2, 5); // 1 to 5 seconds
            sleep($delay);
        }
    }
}

/**
 * Record every login attempt (success or failure) in the database.
 *
 * @param string $email   Email used in attempt
 * @param bool   $success True if login was successful
 */
function record_login_attempt(string $email, bool $success): void {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (ip_address, email, success, attempted_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$ip, $email, $success ? 1 : 0]);

    // Cleanup old records (10% chance on every attempt to avoid performance impact)
    if (mt_rand(1, 100) <= 10) {
        $pdo->prepare("
            DELETE FROM login_attempts 
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ")->execute();
    }
}

/**
 * Detect suspicious behavior (one IP trying many different emails).
 *
 * @return bool
 */
function is_suspicious_ip(): bool {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT email) as unique_emails
        FROM login_attempts
        WHERE ip_address = ?
        AND success = 0
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 300 SECOND)
        AND email IS NOT NULL AND email != ''
    ");
    $stmt->execute([$ip]);
    $result = $stmt->fetch();

    return ($result && $result['unique_emails'] > 5);
}

/**
 * Clear old failed attempts for a specific email after successful login.
 * Helps maintain clean records.
 *
 * @param string $email
 */
function cleanup_old_attempts(string $email): void {
    global $pdo;
    $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE email = ? 
        AND success = 0 
        AND attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ")->execute([$email]);
}
?>

