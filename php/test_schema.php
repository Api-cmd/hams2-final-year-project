<?php
require_once 'config.php';

echo "<h2>Testing HAMS2 Database Schema</h2>";

echo "<h3>1. DESCRIBE doctors;</h3>";
try {
    $stmt = $pdo->query("DESCRIBE doctors");
    $doctorsCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($doctorsCols as $col) {
        echo "<tr>";
        foreach ($col as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>ERROR (doctors): " . $e->getMessage() . "</p>";
}

echo "<h3>2. DESCRIBE time_slots;</h3>";
try {
    $stmt = $pdo->query("DESCRIBE time_slots");
    $slotsCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($slotsCols as $col) {
        echo "<tr>";
        foreach ($col as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>ERROR (time_slots): " . $e->getMessage() . "</p>";
}

echo "<h3>3. DESCRIBE departments;</h3>";
try {
    $stmt = $pdo->query("DESCRIBE departments");
    $deptsCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($deptsCols as $col) {
        echo "<tr>";
        foreach ($col as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color:red'>ERROR (departments): " . $e->getMessage() . "</p>";
}
?>