<?php
// ===========================================================
// php/admin_get_announcements.php
// Returns all announcements for admin management.
//
// CALLED BY: admin announcements management page
// REQUIRES:  Admin must be logged in
// RETURNS: JSON array of announcement objects
// ===========================================================

require_once 'config.php';
require_role('admin');

$sql = "
    SELECT announcement_id, title, content, posted_date, is_active, created_at
    FROM announcements
    ORDER BY posted_date DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json($announcements);
} catch (PDOException $e) {
    error_log('[HAMS] admin_get_announcements.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to load announcements.'], 500);
}