<?php
/**
 * Audit Log Helper
 * Records scheduling actions for accountability and troubleshooting.
 * Include this file in any admin endpoint that modifies scheduling data.
 */

/**
 * Record an action in the audit log.
 *
 * @param PDO    $pdo         Database connection
 * @param int    $adminId     Admin user ID performing the action
 * @param string $action      Action name (e.g. 'template_created', 'slots_generated')
 * @param string $entityType  Entity type (e.g. 'schedule_template', 'holiday', 'exception')
 * @param int|null $entityId  ID of the affected entity
 * @param string $description Human-readable description
 * @param array|null $oldValues Previous state (for updates/deletes)
 * @param array|null $newValues New state (for creates/updates)
 */
function audit_log(
    PDO $pdo,
    int $adminId,
    string $action,
    string $entityType,
    ?int $entityId = null,
    string $description = '',
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (admin_id, action, entity_type, entity_id, description, old_values, new_values)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $adminId,
            $action,
            $entityType,
            $entityId,
            $description,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
        ]);
    } catch (PDOException $e) {
        // Audit logging should never break the main operation.
        // Log the error silently and continue.
        error_log('[HAMS] Audit log write failed: ' . $e->getMessage());
    }
}