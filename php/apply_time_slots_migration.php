<?php
// PHP script to run time_slots migration
require_once 'config.php';

echo "<h2>Applying Time Slots Migration</h2>";

try {
    $sqlStatements = [
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS capacity INT NOT NULL DEFAULT 1;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS booked_count INT NOT NULL DEFAULT 0;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS is_booked TINYINT(1) NOT NULL DEFAULT 0;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS template_id INT NULL;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;",
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_dept_date (dept_id, slot_date);",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_doctor_date (doctor_id, slot_date);",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_booked (is_booked);",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_active (is_active);",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_date_time (slot_date, start_time);",
        "ALTER TABLE time_slots ADD UNIQUE INDEX IF NOT EXISTS idx_unique_slot (dept_id, doctor_id, slot_date, start_time);"
    ];

    foreach ($sqlStatements as $index => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "<p style='color:green'><b>✓ Step " . ($index + 1) . ":</b> " . htmlspecialchars($sql) . "</p>";
    }

    echo "<h2 style='color:green'>✅ Migration completed successfully!</h2>";
    echo "<p>Your time_slots table now has all required columns!</p>";

    echo "<h3>Now testing the slot query:</h3>";
    $testSql = "SELECT * FROM time_slots LIMIT 1";
    $testStmt = $pdo->prepare($testSql);
    $testStmt->execute();
    $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
    if ($testResult) {
        echo "<p style='color:blue'>Test query returned data!</p>";
        echo "<pre>"; print_r($testResult); echo "</pre>";
    } else {
        echo "<p style='color:blue'>No data in time_slots yet, but table is correct!</p>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Migration failed!</h2>";
    echo "<p style='color:red'><b>Error Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:red'><b>File:</b> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color:red'><b>Line:</b> " . htmlspecialchars($e->getLine()) . "</p>";
}
?>