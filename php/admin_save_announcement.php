<?php
// ===========================================================
// php/admin_save_announcement.php
// Creates or updates a hospital announcement.
//
// CALLED BY: admin announcements management page
// REQUIRES:  Admin must be logged in
// METHOD:    POST
// BODY:      JSON { announcement_id?, title, content, posted_date, is_active }
// ===========================================================

require_once 'config.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['title']) || empty($data['content'])) {
    send_json(['error' => 'Title and content are required.'], 400);
}

$title      = clean($data['title']);
$content    = clean($data['content']);
$posted_date = !empty($data['posted_date']) ? clean($data['posted_date']) : date('Y-m-d');
$is_active  = isset($data['is_active']) ? (int)$data['is_active'] : 1;

try {
    if (!empty($data['announcement_id'])) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET title = :title, content = :content, posted_date = :posted_date, is_active = :is_active
            WHERE announcement_id = :id
        ");
        $stmt->bindValue(':id', (int)$data['announcement_id'], PDO::PARAM_INT);
    } else {
        // Create new
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, posted_date, is_active)
            VALUES (:title, :content, :posted_date, :is_active)
        ");
    }
    
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':content', $content, PDO::PARAM_STR);
    $stmt->bindValue(':posted_date', $posted_date, PDO::PARAM_STR);
    $stmt->bindValue(':is_active', $is_active, PDO::PARAM_INT);
    $stmt->execute();
    
    send_json(['success' => true]);
} catch (PDOException $e) {
    error_log('[HAMS] admin_save_announcement.php error: ' . $e->getMessage());
    send_json(['error' => 'Failed to save announcement.'], 500);
}