<?php
// Returns users list. Optional ?limit=5 for dashboard preview.
require_once 'config.php';
require_role('admin');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, phone, role, is_active, created_at
    FROM users
    WHERE role != 'admin'
    ORDER BY created_at DESC
    LIMIT ?
");
// Bind limit as integer explicitly
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();

send_json($stmt->fetchAll());
?>