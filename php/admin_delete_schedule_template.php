<?php
// ===========================================================
// php/admin_delete_schedule_template.php
// Deletes a schedule template and all related records.
// Blocks deletion if template has future booked appointments.
// ===========================================================
require_once 'config.php';
require_once 'audit_log.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['template_id'])) {
    send_json(['error' => 'Template ID is required.'], 400);
}

$templateId = (int)$body['template_id'];
$force = isset($body['force']) ? (bool)$body['force'] : false;

try {
    // Load template info
    $stmt = $pdo->prepare("SELECT * FROM schedule_templates WHERE template_id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) {
        send_json(['error' => 'Template not found.'], 404);
    }

    // Check for future booked appointments unless force
    if (!$force) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS booked_count
            FROM time_slots
            WHERE template_id = ?
              AND slot_date >= CURDATE()
              AND is_booked = 1
        ");
        $stmt->execute([$templateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$result['booked_count'] > 0) {
            send_json([
                'error' => 'This template has ' . $result['booked_count'] . ' future booked appointment(s). Delete these appointments first or use force delete (which will also delete future unbooked slots).',
                'has_bookings' => true,
                'booked_count' => (int)$result['booked_count'],
            ], 409);
        }
    }

    $pdo->beginTransaction();

    // Delete future unbooked slots (keep historical booked ones)
    $pdo->prepare("
        DELETE FROM time_slots
        WHERE template_id = ?
          AND slot_date >= CURDATE()
          AND is_booked = 0
    ")->execute([$templateId]);

    // Delete template-related records
    $pdo->prepare("DELETE FROM template_day_sessions WHERE template_id = ?")->execute([$templateId]);
    $pdo->prepare("DELETE FROM template_days WHERE template_id = ?")->execute([$templateId]);
    $pdo->prepare("DELETE FROM template_holidays WHERE template_id = ?")->execute([$templateId]);

    // Delete the template itself
    $pdo->prepare("DELETE FROM schedule_templates WHERE template_id = ?")->execute([$templateId]);

    $pdo->commit();

    audit_log(
        $pdo,
        (int)$_SESSION['user_id'],
        'template_deleted',
        'schedule_template',
        $templateId,
        'Deleted template: ' . $template['template_name'] . ' for doctor ID ' . $template['doctor_id'],
        $template,
        null
    );

    send_json(['success' => true, 'message' => 'Template deleted successfully.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[HAMS] Error deleting schedule template: ' . $e->getMessage());
    send_json(['error' => 'Failed to delete schedule template', 'detail' => $e->getMessage()], 500);
}