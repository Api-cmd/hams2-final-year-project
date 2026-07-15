<?php
// PHP script to add doctor_id to time_slots table
require_once 'config.php';

echo "<h2>Adding doctor_id to time_slots table</h2>";

try {
    $sqlStatements = [
        "ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS doctor_id INT NULL AFTER dept_id;",
        "ALTER TABLE time_slots ADD CONSTRAINT IF NOT EXISTS fk_time_slots_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL;",
        "ALTER TABLE time_slots ADD INDEX IF NOT EXISTS idx_doctor_date (doctor_id, slot_date);"
    ];

    foreach ($sqlStatements as $index => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "<p style='color:green'><b>✓ Step " . ($index + 1) . ":</b> " . htmlspecialchars($sql) . "</p>";
    }

    echo "<h2 style='color:green'>✅ Migration completed successfully!</h2>";
    echo "<p>Now checking time_slots table structure:</p>";
    $describeStmt = $pdo->prepare("DESCRIBE time_slots");
    $describeStmt->execute();
    $columns = $describeStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        foreach ($col as $value) echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Migration failed!</h2>";
    echo "<p style='color:red'><b>Error Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>