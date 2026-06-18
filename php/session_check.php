<?php
// ===========================================================
// php/session_check.php
// Returns the current login state as JSON.
//
// CALLED BY: Every HTML page via fetch() on load.
// PURPOSE:   HTML pages cannot read PHP sessions directly.
//            So JS calls this file to find out:
//              - Is the user logged in?
//              - What is their name and role?
//            Based on the answer, JS either shows the page
//            or redirects to the login page.
//
// RETURNS JSON:
//   { "logged_in": true,  "name": "James", "role": "patient" }
//   { "logged_in": false }
// ===========================================================

require_once 'config.php';

if (is_logged_in()) {
    send_json([
        'logged_in' => true,
        'user_id'   => $_SESSION['user_id'],
        'name'      => $_SESSION['user_name'],
        'role'      => $_SESSION['user_role'],
        'email'     => $_SESSION['user_email'],
    ]);
} else {
    send_json(['logged_in' => false]);
}
?>