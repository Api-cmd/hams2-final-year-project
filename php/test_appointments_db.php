<?php
// ===========================================================
// TEST SCRIPT: Diagnose appointment data issues
// ===========================================================

require_once 'config.php';

// Test 1: Check if appointments table exists and has data
echo "=== TEST 1: Appointments Table ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $result = $stmt->fetch();
    echo "Total appointments: " . $result['count'] . "\n";
    
    // Show first 5 appointments
    $stmt = $pdo->query("SELECT * FROM appointments LIMIT 5");
    $appointments = $stmt->fetchAll();
    echo "Sample appointments: " . json_encode($appointments, JSON_PRETTY_PRINT) . "\n\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Check if the admin query works
echo "=== TEST 2: Admin Get Appointments Query ===\n";
try {
    $stmt = $pdo->query("
        SELECT
            a.appt_id,
            a.status,
            a.notes,
            a.created_at,
            s.slot_date,
            s.start_time,
            s.end_time,
            u_p.full_name AS patient_name,
            u_d.full_name AS doctor_name,
            d.dept_name
        FROM appointments a
        JOIN time_slots  s   ON a.slot_id          = s.slot_id
        JOIN users       u_p ON a.patient_user_id  = u_p.user_id
        JOIN doctors     dr  ON a.doctor_id        = dr.doctor_id
        JOIN users       u_d ON dr.user_id         = u_d.user_id
        JOIN departments d   ON a.dept_id          = d.dept_id
        ORDER BY s.slot_date DESC, s.start_time DESC
        LIMIT 500
    ");
    
    $appointments = $stmt->fetchAll();
    echo "Retrieved " . count($appointments) . " appointments\n";
    if (count($appointments) > 0) {
        echo "First appointment: " . json_encode($appointments[0], JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "This error means the query has a syntax problem or references non-existent tables/columns.\n";
}

echo "\n=== TEST 3: Table Structure Check ===\n";
try {
    $tables = ['appointments', 'time_slots', 'users', 'doctors', 'departments'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "$table: " . $result['count'] . " rows\n";
    }
} catch (Exception $e) {
    echo "ERROR checking tables: " . $e->getMessage() . "\n";
}

echo "\n=== TEST 4: Doctors and Users Join ===\n";
try {
    $stmt = $pdo->query("
        SELECT 
            dr.doctor_id,
            dr.user_id,
            u.full_name,
            u.user_id as user_user_id
        FROM doctors dr
        LEFT JOIN users u ON dr.user_id = u.user_id
        LIMIT 5
    ");
    $results = $stmt->fetchAll();
    echo "Doctors sample: " . json_encode($results, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
