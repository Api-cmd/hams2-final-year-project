<?php
// Generate multiple time slots in bulk, or toggle one slot.
// HAMS2: Slots are department-based, not doctor-specific
// UPDATED: Supports date range generation (e.g., for a full month)
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
// Logic: In HAMS2, slots are created for departments, not individual doctors
// Patients can then optionally choose a doctor when booking
// NEW: Supports date range (start_date to end_date) for monthly generation
$dept_id    = (int)($body['dept_id']    ?? 0);
$doctor_id  = isset($body['doctor_id']) ? (int)$body['doctor_id'] : 0;
$start_date = clean($body['start_date'] ?? $body['date'] ?? '');
$end_date   = clean($body['end_date']   ?? '');
$start_time = clean($body['start']      ?? '');
$end_time   = clean($body['end']        ?? '');
$duration   = (int)($body['duration']   ?? 10); // Default to 10 minutes
// Single-patient slots enforced: capacity fixed to 1
$capacity   = 1;

if ((!$dept_id && !$doctor_id) || !$start_date || !$start_time || !$end_time || !$duration) {
    send_json(['error' => 'Doctor or department, start date, times, and duration are required.'], 400);
}

// If end_date is not provided, use start_date (single day generation)
if (!$end_date) {
    $end_date = $start_date;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    send_json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
}

// If doctor_id is provided, derive the department from the doctor profile.
if ($doctor_id > 0) {
    $stmt = $pdo->prepare("SELECT dept_id FROM doctors WHERE doctor_id = ? AND is_active = 1");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctor) {
        send_json(['error' => 'Selected doctor is not valid.'], 400);
    }
    $dept_id = (int)$doctor['dept_id'];
}

// Prevent past dates: allow only today and future dates
$today = date('Y-m-d');
if (strtotime($end_date) < strtotime($today)) {
    send_json(['error' => 'End date cannot be in the past.'], 400);
}

if (strtotime($start_date) > strtotime($end_date)) {
    send_json(['error' => 'Start date cannot be after end date.'], 400);
}

// Limit date range to prevent excessive generation (max 90 days)
$days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
if ($days_diff > 90) {
    send_json(['error' => 'Date range cannot exceed 90 days.'], 400);
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

$startMins = $toMins($start_time);
$endMins   = $toMins($end_time);

if ($endMins <= $startMins) {
    send_json(['error' => 'End time must be after start time.'], 400);
}

// Prepare the INSERT statement once and execute it in a loop
// This is more efficient than preparing inside the loop
$stmt    = $pdo->prepare("
    INSERT IGNORE INTO time_slots (dept_id, doctor_id, slot_date, start_time, end_time, capacity)
    VALUES (?, ?, ?, ?, ?, ?)
");

$duplicateStmt = $pdo->prepare(
    "SELECT 1 FROM time_slots WHERE dept_id = ? AND slot_date = ? AND start_time = ? " .
    ($doctor_id > 0 ? "AND doctor_id = ?" : "AND doctor_id IS NULL")
);

$created = 0;
$skipped_dates = [];

// Loop through each date in the range
$current_date = $start_date;
while (strtotime($current_date) <= strtotime($end_date)) {
    // Skip weekends if configured (optional - can be added as parameter)
    // $day_of_week = date('N', strtotime($current_date));
    // if ($day_of_week >= 6 && isset($body['skip_weekends']) && $body['skip_weekends']) {
    //     $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    //     continue;
    // }

    // If creating slots for today, do not create slots that start in the past
    $current = $startMins;
    if ($current_date === $today) {
        $now = new DateTime('now');
        $nowMins = (int)$now->format('H') * 60 + (int)$now->format('i');
        // Advance current to the first slot that is strictly in the future
        if ($current <= $nowMins) {
            // Move current forward to the next multiple of duration after now
            $remainder = ($nowMins - $current) % $duration;
            $current = $nowMins + ($remainder === 0 ? $duration : ($duration - $remainder));
        }
    }

    $day_created = 0;
    while ($current + $duration <= $endMins) {
        $slotStart = $toTime($current);
        $slotEnd   = $toTime($current + $duration);

        $duplicateParams = [$dept_id, $current_date, $slotStart];
        if ($doctor_id > 0) {
            $duplicateParams[] = $doctor_id;
        }

        $duplicateStmt->execute($duplicateParams);
        if ($duplicateStmt->fetch()) {
            $current += $duration;
            continue;
        }

        $stmt->execute([$dept_id, $doctor_id > 0 ? $doctor_id : null, $current_date, $slotStart, $slotEnd, 1]);

        if ($stmt->rowCount() > 0) {
            $created++;
            $day_created++;
        }

        $current += $duration; // Move to the next slot
    }

    // Track dates that had no new slots created (all duplicates)
    if ($day_created === 0 && $current_date !== $today) {
        $skipped_dates[] = $current_date;
    }

    // Move to next day
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}

if ($created === 0) {
    send_json(['error' => 'No new slots created. All slots may already exist for this date range.'], 400);
}

$response = [
    'success' => true,
    'created' => $created,
    'date_range' => "$start_date to $end_date",
    'days_processed' => $days_diff + 1
];

if (!empty($skipped_dates)) {
    $response['skipped_dates'] = $skipped_dates;
    $response['message'] = "Created $created slots. Some dates were skipped (slots already exist).";
} else {
    $response['message'] = "Successfully created $created slots for $days_diff day(s).";
}

send_json($response);
?>