<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $usersStmt = $pdo->query("SELECT user_id, full_name, email, role FROM users");
    $doctorsStmt = $pdo->query("SELECT doctor_id, full_name, dept_id FROM doctors");
    
    echo json_encode([
        'users' => $usersStmt->fetchAll(),
        'doctors' => $doctorsStmt->fetchAll()
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
