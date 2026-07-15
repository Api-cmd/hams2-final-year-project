<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $results = [];
    
    // Check schedule_templates table
    $stmt = $pdo->query("SHOW TABLES LIKE 'schedule_templates'");
    $results['schedule_templates_exists'] = $stmt->rowCount() > 0;
    
    if ($results['schedule_templates_exists']) {
        $stmt = $pdo->query("DESCRIBE schedule_templates");
        $results['schedule_templates_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check template_days table
    $stmt = $pdo->query("SHOW TABLES LIKE 'template_days'");
    $results['template_days_exists'] = $stmt->rowCount() > 0;
    
    // Check template_holidays table
    $stmt = $pdo->query("SHOW TABLES LIKE 'template_holidays'");
    $results['template_holidays_exists'] = $stmt->rowCount() > 0;
    
    // Check holidays table
    $stmt = $pdo->query("SHOW TABLES LIKE 'holidays'");
    $results['holidays_exists'] = $stmt->rowCount() > 0;
    
    // Check schedule_exceptions table
    $stmt = $pdo->query("SHOW TABLES LIKE 'schedule_exceptions'");
    $results['schedule_exceptions_exists'] = $stmt->rowCount() > 0;
    
    // Check time_slots columns
    $stmt = $pdo->query("DESCRIBE time_slots");
    $results['time_slots_columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
