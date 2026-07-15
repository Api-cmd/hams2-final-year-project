<?php
// Debug version of admin_get_slots.php with no auth requirement
require_once 'config.php';

echo "<h2>Debugging admin_get_slots.php (no auth)</h2>";

try {
    $dept_id = (int)($_GET['dept_id'] ?? 0);
    $date    = isset($_GET['date']) ? clean((string)$_GET['date']) : '';
    echo "<p>dept_id: " . htmlspecialchars($dept_id) . "</p>";
    echo "<p>date: " . htmlspecialchars($date) . "</p>";

    $whereClauses = [];
    $params = [];

    if ($dept_id > 0) {
        $whereClauses[] = 'ts.dept_id = :dept_id';
        $params[':dept_id'] = $dept_id;
    }

    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $whereClauses[] = 'ts.slot_date = :date';
        $params[':date'] = $date;
    }

    $whereClauses[] = '(ts.slot_date > CURDATE() OR (ts.slot_date = CURDATE() AND ts.end_time > CURTIME()))';

    if (isset($_GET['doctor_id']) && (int)$_GET['doctor_id'] > 0) {
        $whereClauses[] = 'ts.doctor_id = :doctor_id';
        $params[':doctor_id'] = (int)$_GET['doctor_id'];
    }

    $whereSql = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

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
        $whereSql
        ORDER BY ts.slot_date DESC, ts.start_time ASC
    ";

    echo "<h3>SQL Query:</h3><pre>";
    echo htmlspecialchars($sql);
    echo "</pre>";

    echo "<h3>Params:</h3><pre>";
    print_r($params);
    echo "</pre>";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Results:</h3><pre>";
    print_r($results);
    echo "</pre>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>PDO ERROR!</h3>";
    echo "<p style='color:red'><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:red'><b>File:</b> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color:red'><b>Line:</b> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4 style='color:red'>Stack Trace:</h4><pre style='color:red'>";
    echo $e->getTraceAsString();
    echo "</pre>";
} catch (TypeError $e) {
    echo "<h3 style='color:red'>TYPE ERROR!</h3>";
    echo "<p style='color:red'><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:red'><b>File:</b> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color:red'><b>Line:</b> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4 style='color:red'>Stack Trace:</h4><pre style='color:red'>";
    echo $e->getTraceAsString();
    echo "</pre>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>EXCEPTION!</h3>";
    echo "<p style='color:red'><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:red'><b>File:</b> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color:red'><b>Line:</b> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<h4 style='color:red'>Stack Trace:</h4><pre style='color:red'>";
    echo $e->getTraceAsString();
    echo "</pre>";
}
?>