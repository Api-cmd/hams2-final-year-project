<?php
// ===========================================================
// php/admin_delete_announcement.php
// Deletes a hospital announcement.
//
// CALLED BY: admin announcements management page
// REQUIRES:  Admin must be logged in
// METHOD:    POST
// BODY:      JSON { announcement_id }
// ===========================================================

require_once 'config.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['announcement_id'])) {
    send_json(['error' => 'Announcement ID is required.'], 400);
}

try {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id");
    $stmt->bindValue(':id', (int)$data['announcement_id'], PDO::PARAM_INT);
    $stmt->execute();
    send_json(['success' => true]);
} catch (PDOException $e) {
    error_log('[HAMS] admin_delete_announcement.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to delete announcement.'], 500);
}