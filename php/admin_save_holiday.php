<?php
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    send_json(['error' => 'Invalid request body. Expected JSON data.'], 400);
}

$action = clean((string)($body['action'] ?? 'save'));

try {
    if ($action === 'delete') {
        $holidayId = (int)($body['holiday_id'] ?? 0);
        if ($holidayId <= 0) {
            send_json(['error' => 'Holiday ID is required for deletion.'], 400);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM holidays WHERE holiday_id = ?");
        $deleteStmt->execute([$holidayId]);

        if ($deleteStmt->rowCount() === 0) {
            send_json(['error' => 'Global holiday not found.'], 404);
        }

        send_json(['success' => true, 'message' => 'Global holiday removed successfully.']);
    }

    $holidayDate = clean((string)($body['holiday_date'] ?? ''));
    $name = clean((string)($body['name'] ?? ''));

    if (!$holidayDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
        send_json(['error' => 'Holiday date is required and must use YYYY-MM-DD format.'], 400);
    }

    if ($name === '') {
        send_json(['error' => 'Holiday name is required.'], 400);
    }

    $existingStmt = $pdo->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ?");
    $existingStmt->execute([$holidayDate]);
    $existingHoliday = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingHoliday) {
        $updateStmt = $pdo->prepare("UPDATE holidays SET name = ? WHERE holiday_id = ?");
        $updateStmt->execute([$name, $existingHoliday['holiday_id']]);

        send_json([
            'success' => true,
            'message' => 'Global holiday updated successfully.',
            'holiday_id' => (int)$existingHoliday['holiday_id'],
        ]);
    }

    $insertStmt = $pdo->prepare("INSERT INTO holidays (holiday_date, name) VALUES (?, ?)");
    $insertStmt->execute([$holidayDate, $name]);

    send_json([
        'success' => true,
        'message' => 'Global holiday added successfully.',
        'holiday_id' => (int)$pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    error_log('[HAMS] Error saving global holiday: ' . $e->getMessage());
    send_json(['error' => 'Failed to save global holiday', 'detail' => $e->getMessage()], 500);
}
?>
