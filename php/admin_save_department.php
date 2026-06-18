<?php
// Add, edit, or toggle a department.
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

$dept_id = (int)($body['dept_id'] ?? 0);

// Toggle active/inactive
if (isset($body['toggle_active'])) {
    $pdo->prepare("UPDATE departments SET is_active=? WHERE dept_id=?")
        ->execute([(int)$body['toggle_active'], $dept_id]);
    send_json(['success' => true]);
}

$name = clean($body['name']        ?? '');
$desc = clean($body['description'] ?? '');

if (!$name) send_json(['error' => 'Department name is required.'], 400);

if ($dept_id) {
    // Edit
    $pdo->prepare("UPDATE departments SET dept_name=?, description=? WHERE dept_id=?")
        ->execute([$name, $desc, $dept_id]);
} else {
    // Add — check name is unique
    $check = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name=?");
    $check->execute([$name]);
    if ($check->fetch()) {
        send_json(['error' => 'A department with that name already exists.'], 409);
    }
    $pdo->prepare("INSERT INTO departments (dept_name, description) VALUES (?,?)")
        ->execute([$name, $desc]);
}

send_json(['success' => true]);
?>