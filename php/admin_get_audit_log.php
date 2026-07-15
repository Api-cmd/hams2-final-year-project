<?php
require_once 'config.php';
require_role('admin');

try {
    $entity_type = clean($_GET['entity_type'] ?? '');
    $entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
    $action = clean($_GET['action'] ?? '');
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

    $whereClauses = [];
    $params = [];

    if ($entity_type) {
        $whereClauses[] = 'a.entity_type = ?';
        $params[] = $entity_type;
    }

    if ($entity_id > 0) {
        $whereClauses[] = 'a.entity_id = ?';
        $params[] = $entity_id;
    }

    if ($action) {
        $whereClauses[] = 'a.action = ?';
        $params[] = $action;
    }

    $whereSql = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Get entries
    $stmt = $pdo->prepare("
        SELECT
            a.log_id,
            a.admin_id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.description,
            a.old_values,
            a.new_values,
            a.created_at,
            u.full_name AS admin_name
        FROM audit_log a
        JOIN users u ON a.admin_id = u.user_id
        $whereSql
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    // Decode JSON fields
    foreach ($entries as &$entry) {
        $entry['old_values'] = $entry['old_values'] ? json_decode($entry['old_values'], true) : null;
        $entry['new_values'] = $entry['new_values'] ? json_decode($entry['new_values'], true) : null;
    }

    send_json([
        'entries' => $entries,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching audit log: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch audit log', 'detail' => $e->getMessage()], 500);
}