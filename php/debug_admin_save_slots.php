<?php
// Debug version of admin_save_slots.php with no auth
require_once 'config.php';

echo "<h2>Debugging admin_save_slots.php (no auth)</h2>";

// Get JSON input
$rawBody = file_get_contents('php://input');
echo "<h3>Raw Body:</h3><pre>" . htmlspecialchars($rawBody) . "</pre>";
try {
    $body = json_decode($rawBody, true);
    if (!$body) {
        echo "<p style='color:red'>ERROR: Invalid request or no JSON body</p>";
        exit;
    }

    echo "<h3>Parsed Body:</h3><pre>"; print_r($body); echo "</pre>";

    // --- Bulk slot generation ---
    $dept_id    = (int)($body['dept_id'] ?? 0);
    $doctor_id  = isset($body['doctor_id']) ? (int)$body['doctor_id'] : 0;
    $start_date = isset($body['start_date']) ? clean((string)$body['start_date']) : (isset($body['date']) ? clean((string)$body['date']) : '');
    $end_date   = isset($body['end_date']) ? clean((string)$body['end_date']) : '';
    $start_time = isset($body['start']) ? clean((string)$body['start']) : '';
    $end_time   = isset($body['end']) ? clean((string)$body['end']) : '';
    $duration   = (int)($body['duration'] ?? 10);
    $capacity   = 1;

    echo "<h3>Parsed Values:</h3>";
    echo "dept_id: " . htmlspecialchars($dept_id) . "<br>";
    echo "doctor_id: " . htmlspecialchars($doctor_id) . "<br>";
    echo "start_date: " . htmlspecialchars($start_date) . "<br>";
    echo "end_date: " . htmlspecialchars($end_date) . "<br>";
    echo "start_time: " . htmlspecialchars($start_time) . "<br>";
    echo "end_time: " . htmlspecialchars($end_time) . "<br>";
    echo "duration: " . htmlspecialchars($duration) . "<br>";

    if ((!$dept_id && !$doctor_id) || !$start_date || !$start_time || !$end_time || !$duration) {
        echo "<p style='color:red'>Validation failed: Doctor or dept, start date, times, duration required!</p>";
        exit;
    }

    if (!$end_date) {
        $end_date = $start_date;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        echo "<p style='color:red'>Validation failed: Invalid date format!</p>";
        exit;
    }

    $targetDoctors = [];
    if ($doctor_id > 0) {
        $stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE doctor_id = ? AND is_active = 1");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doctor) {
            echo "<p style='color:red'>Validation failed: Selected doctor invalid!</p>";
            exit;
        }
        $targetDoctors[] = $doctor;
        $dept_id = (int)$doctor['dept_id'];
    } else {
        echo "<p>Querying doctors for dept_id " . htmlspecialchars($dept_id) . "</p>";
        $stmt = $pdo->prepare("SELECT doctor_id, dept_id FROM doctors WHERE dept_id = ? AND is_active = 1 ORDER BY doctor_id");
        $stmt->execute([$dept_id]);
        $targetDoctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($targetDoctors)) {
            echo "<p style='color:red'>Validation failed: No active doctors in this department!</p>";
            exit;
        }
        echo "<h3>Target Doctors:</h3><pre>"; print_r($targetDoctors); echo "</pre>";
    }

    $today = date('Y-m-d');
    if (strtotime($end_date) < strtotime($today)) {
        echo "<p style='color:red'>Validation failed: End date cannot be in past!</p>";
        exit;
    }
    if (strtotime($start_date) > strtotime($end_date)) {
        echo "<p style='color:red'>Validation failed: Start date cannot be after end date!</p>";
        exit;
    }
    $days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
    if ($days_diff > 90) {
        echo "<p style='color:red'>Validation failed: Date range cannot exceed 90 days!</p>";
        exit;
    }

    $toMins = function(string $t): int {
        [$h, $m] = explode(':', $t);
        return (int)$h * 60 + (int)$m;
    };
    $toTime = function(int $mins): string {
        return sprintf('%02d:%02d:00', intdiv($mins, 60), $mins % 60);
    };
    $startMins = $toMins($start_time);
    $endMins = $toMins($end_time);
    if ($endMins <= $startMins) {
        echo "<p style='color:red'>Validation failed: End time must be after start time!</p>";
        exit;
    }

    echo "<p style='color:green'>All validations passed!</p>";
    echo "<h3>Success! The test is complete without errors!</h3>";

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