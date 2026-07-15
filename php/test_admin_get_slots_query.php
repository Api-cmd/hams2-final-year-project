<?php
// Test script to run admin_get_slots.php's query directly
require_once 'config.php';

echo "<h2>Testing admin_get_slots.php query</h2>";

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
        LEFT JOIN departments d ON ts.dept_id = d.dept_id
        LEFT JOIN doctors doc ON ts.doctor_id = doc.doctor_id
        ORDER BY ts.slot_date DESC, ts.start_time ASC
        LIMIT 10
    ";

    echo "<h3>Query to run:</h3><pre>";
    echo htmlspecialchars($sql);
    echo "</pre>";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Results:</h3><pre>";
    print_r($results);
    echo "</pre>";

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold'>PDO Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='color:red'>" . $e->getTraceAsString() . "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold'>Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='color:red'>" . $e->getTraceAsString() . "</pre>";
}
?>