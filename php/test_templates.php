<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM schedule_templates");
    $templates = $stmt->fetchAll();
    
    $stmt2 = $pdo->query("SELECT * FROM template_days");
    $days = $stmt2->fetchAll();
    
    echo json_encode([
        'schedule_templates' => $templates,
        'template_days' => $days
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
