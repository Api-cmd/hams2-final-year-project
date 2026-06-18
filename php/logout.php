<?php
// ===========================================================
// php/logout.php
// Destroys the session and sends the user to the login page.
// CALLED BY: Logout link in every navbar
// ===========================================================

require_once 'config.php';

// Clear all session variables
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destroy the session on the server
session_destroy();

header('Location: ../index.html?msg=logged_out');
exit();
?>