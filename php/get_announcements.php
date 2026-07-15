<?php
// ===========================================================
// php/get_announcements.php
// Returns active hospital announcements for the patient dashboard.
//
// CALLED BY: pages/dashboard.html
// RETURNS: JSON array of announcement objects
// ===========================================================

require_once 'config.php';

$sql = "
    SELECT announcement_id, title, content, posted_date
    FROM announcements
    WHERE is_active = 1
    ORDER BY posted_date DESC
    LIMIT 10
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json($announcements);
} catch (PDOException $e) {
    error_log('[HAMS] get_announcements.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to load announcements.'], 500);
}