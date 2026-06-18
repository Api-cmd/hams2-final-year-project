<?php
// ============================================================
// php/generate_slots.php
// Reads doctor_schedules table and generates time_slots
// for the next 7 days for every active doctor.
//
// HOW TO RUN:
//   Manual:    visit http://localhost/hams/php/generate_slots.php
//              while logged in as admin
//   Automatic: on a real server you would set up a cron job
//              to run this every day at midnight:
//              0 0 * * * php /path/to/generate_slots.php
//
// SAFE TO RUN MULTIPLE TIMES:
//   Uses INSERT IGNORE so existing slots are never duplicated.
// ============================================================

require_once 'config.php';
require_role('admin');

// How many days ahead to generate slots for
// 7 means today + the next 6 days
$days_ahead = 7;

$generated = 0;  // Count of new slots created
$skipped   = 0;  // Count of slots already existing (INSERT IGNORE skipped them)

// Helper: convert HH:MM:SS time string to total minutes
// e.g. "08:30:00" → 510
function timeToMins(string $t): int {
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}

// Helper: convert total minutes back to "HH:MM:SS"
// e.g. 510 → "08:30:00"
function minsToTime(int $mins): string {
    return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
}

// Prepare the INSERT once — execute it in the loop for each slot
// INSERT IGNORE silently skips if the UNIQUE key (doctor+date+start) already exists
$insert = $pdo->prepare("
    INSERT IGNORE INTO time_slots (doctor_id, slot_date, start_time, end_time)
    VALUES (?, ?, ?, ?)
");

// Loop through each of the next 7 days
for ($i = 0; $i < $days_ahead; $i++) {

    // Get the date and day of week for this iteration
    // date('w') returns 0=Sunday through 6=Saturday — matches our DB column
    $date        = date('Y-m-d', strtotime("+{$i} days"));
    $day_of_week = (int)date('w', strtotime($date));

    // Get all doctor schedules for this day of the week
    $stmt = $pdo->prepare("
        SELECT
            ds.doctor_id,
            ds.start_time,
            ds.end_time,
            ds.slot_duration,
            ds.is_working
        FROM doctor_schedules ds
        JOIN doctors  dr ON ds.doctor_id = dr.doctor_id
        JOIN users    u  ON dr.user_id   = u.user_id
        WHERE ds.day_of_week = ?
          AND ds.is_working  = 1    -- skip days off
          AND u.is_active    = 1    -- skip disabled doctor accounts
    ");
    $stmt->execute([$day_of_week]);
    $schedules = $stmt->fetchAll();

    foreach ($schedules as $schedule) {
        $startMins = timeToMins($schedule['start_time']);
        $endMins   = timeToMins($schedule['end_time']);
        $duration  = (int)$schedule['slot_duration'];
        $current   = $startMins;

        // Generate one slot per duration block within working hours
        while ($current + $duration <= $endMins) {
            $slotStart = minsToTime($current);
            $slotEnd   = minsToTime($current + $duration);

            $insert->execute([
                $schedule['doctor_id'],
                $date,
                $slotStart,
                $slotEnd,
            ]);

            // rowCount() returns 1 if inserted, 0 if INSERT IGNORE skipped it
            if ($insert->rowCount() > 0) {
                $generated++;
            } else {
                $skipped++;
            }

            $current += $duration;
        }
    }
}

send_json([
    'success'   => true,
    'generated' => $generated,
    'skipped'   => $skipped,
    'message'   => "{$generated} slots created, {$skipped} already existed.",
]);
?>