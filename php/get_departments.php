<?php
// ===========================================================
// php/get_departments.php
// Returns all active departments as JSON.
// CALLED BY: pages/book.html on page load
// ===========================================================

require_once 'config.php';
require_login();

$stmt = $pdo->query("
    SELECT dept_id, dept_name, description
    FROM departments
    WHERE is_active = 1
    ORDER BY dept_name ASC
");

send_json($stmt->fetchAll());
?>