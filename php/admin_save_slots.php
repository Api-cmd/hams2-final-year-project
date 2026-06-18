<?php
// Generate multiple time slots in bulk, or toggle one slot.
require_once 'config.php';
require_role('admin');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) send_json(['error' => 'Invalid request.'], 400);

// --- Toggle a single slot active/inactive ---
if (isset($body['slot_id']) && isset($body['toggle_active'])) {
    $stmt = $pdo->prepare("UPDATE time_slots SET is_active=? WHERE slot_id=? AND is_booked=0");
    $stmt->execute([(int)$body['toggle_active'], (int)$body['slot_id']]);
    send_json(['success' => true]);
}

// --- Bulk slot generation ---
$doctor_id = (int)($body['doctor_id'] ?? 0);
$date      = clean($body['date']      ?? '');
$start     = clean($body['start']     ?? '');
$end       = clean($body['end']       ?? '');
$duration  = (int)($body['duration']  ?? 30);

if (!$doctor_id || !$date || !$start || !$end || !$duration) {
    send_json(['error' => 'All fields are required.'], 400);
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    send_json(['error' => 'Invalid date format.'], 400);
}

// Prevent past dates: allow only today and future dates
$today = date('Y-m-d');
if (strtotime($date) < strtotime($today)) {
    send_json(['error' => 'Date cannot be in the past.'], 400);
}

// Convert HH:MM time strings to total minutes for arithmetic
// e.g. "08:30" becomes 8*60 + 30 = 510 minutes
$toMins = function(string $t): int {
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
};

// Convert total minutes back to "HH:MM:SS" for MySQL TIME column
$toTime = function(int $mins): string {
    return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
};

$startMins = $toMins($start);
$endMins   = $toMins($end);

if ($endMins <= $startMins) {
    send_json(['error' => 'End time must be after start time.'], 400);
}

// Prepare the INSERT statement once and execute it in a loop
// This is more efficient than preparing inside the loop
$stmt    = $pdo->prepare("
    INSERT IGNORE INTO time_slots (doctor_id, slot_date, start_time, end_time)
    VALUES (?, ?, ?, ?)
");
// INSERT IGNORE skips the row silently if the UNIQUE key already exists
// (same doctor + date + start_time), preventing duplicate slots

$created = 0;

// If creating slots for today, do not create slots that start in the past
$current = $startMins;
if ($date === $today) {
    $now = new DateTime('now');
    $nowMins = (int)$now->format('H') * 60 + (int)$now->format('i');
    // Advance current to the first slot that is strictly in the future
    if ($current <= $nowMins) {
        // Move current forward to the next multiple of duration after now
        $remainder = ($nowMins - $current) % $duration;
        $current = $nowMins + ($remainder === 0 ? $duration : ($duration - $remainder));
    }
}

while ($current + $duration <= $endMins) {
    $slotStart = $toTime($current);
    $slotEnd   = $toTime($current + $duration);

    $stmt->execute([$doctor_id, $date, $slotStart, $slotEnd]);

    if ($stmt->rowCount() > 0) $created++;

    $current += $duration; // Move to the next slot
}

if ($created === 0) {
    send_json(['error' => 'No future slots created. Please choose a later time or date.'], 400);
}

send_json(['success' => true, 'created' => $created]);
?>