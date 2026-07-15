<?php
require_once 'config.php';

echo "Testing admin_get_slots.php query...\n";

try {
    $sql = "
        SELECT
            ts.slot_id,
            ts.slot_date,
            ts.start_time,
            ts.end_time,
            ts.capacity,
            ts.booked_count,
            ts.is_booked,
            ts.is_active,
            ts.doctor_id,
            d.dept_name,
            doc.full_name AS doctor_name,
            GREATEST(ts.capacity - ts.booked_count, 0) AS available_count
        FROM time_slots ts
        JOIN departments d ON ts.dept_id = d.dept_id
        LEFT JOIN doctors doc ON ts.doctor_id = doc.doctor_id
        ORDER BY ts.slot_date DESC, ts.start_time ASC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll();
    echo "Query executed successfully!\n";
    echo "Found " . count($results) . " results!\n";
    print_r($results);
} catch (PDOException $e) {
    echo "PDO Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>