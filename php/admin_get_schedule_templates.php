<?php
require_once 'config.php';
require_role('admin');

try {
    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
    $dept_id   = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
    $month     = clean($_GET['month'] ?? '');
    $include_inactive = isset($_GET['include_inactive']) ? (bool)$_GET['include_inactive'] : false;

    $whereClauses = [];
    $params = [];

    if ($doctor_id > 0) {
        $whereClauses[] = 't.doctor_id = ?';
        $params[] = $doctor_id;
    }

    if ($dept_id > 0) {
        $whereClauses[] = 't.dept_id = ?';
        $params[] = $dept_id;
    }

    if (!$include_inactive) {
        $whereClauses[] = 't.is_active = 1';
    }

    $whereSql = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $templates = $pdo->prepare("
        SELECT
            t.template_id,
            t.template_name,
            t.doctor_id,
            t.dept_id,
            t.slot_duration,
            t.is_active,
            t.effective_from,
            t.effective_to,
            doc.full_name AS doctor_name,
            dept.dept_name
        FROM schedule_templates t
        JOIN doctors doc ON t.doctor_id = doc.doctor_id
        JOIN departments dept ON t.dept_id = dept.dept_id
        $whereSql
        ORDER BY dept.dept_name ASC, doc.full_name ASC, t.effective_from ASC, t.template_name ASC
    ");
    $templates->execute($params);
    $templates = $templates->fetchAll();

    if (empty($templates)) {
        send_json([]);
    }

    $templateIds = array_column($templates, 'template_id');
    $placeholders = implode(',', array_fill(0, count($templateIds), '?'));

    $dayStmt = $pdo->prepare("
        SELECT template_id, day_of_week, is_working, start_time, end_time, break_start, break_end
        FROM template_days
        WHERE template_id IN ($placeholders)
        ORDER BY template_id, day_of_week
    ");
    $dayStmt->execute($templateIds);
    $days = $dayStmt->fetchAll();

    $holidayStmt = $pdo->prepare("
        SELECT template_id, holiday_date, note
        FROM template_holidays
        WHERE template_id IN ($placeholders)
        ORDER BY holiday_date ASC
    ");
    $holidayStmt->execute($templateIds);
    $holidays = $holidayStmt->fetchAll();

    $statsStmt = $pdo->prepare("
        SELECT
            template_id,
            COUNT(*) AS total_slots,
            SUM(is_booked) AS total_booked,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_slots,
            SUM(CASE WHEN is_active = 1 AND is_booked = 0 THEN 1 ELSE 0 END) AS available_slots
        FROM time_slots
        WHERE template_id IN ($placeholders)
        GROUP BY template_id
    ");
    $statsStmt->execute($templateIds);
    $statsRows = $statsStmt->fetchAll();

    $monthStats = [];
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $monthStatsStmt = $pdo->prepare("
            SELECT
                template_id,
                COUNT(*) AS total_slots,
                SUM(is_booked) AS total_booked,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_slots,
                SUM(CASE WHEN is_active = 1 AND is_booked = 0 THEN 1 ELSE 0 END) AS available_slots
            FROM time_slots
            WHERE template_id IN ($placeholders)
              AND slot_date BETWEEN ? AND ?
            GROUP BY template_id
        ");

        $monthStatsStmt->execute(array_merge($templateIds, [$monthStart, $monthEnd]));
        foreach ($monthStatsStmt->fetchAll() as $row) {
            $monthStats[$row['template_id']] = $row;
        }
    }

    $sessionStmt = $pdo->prepare("
        SELECT session_id, template_id, day_of_week, start_time, end_time, sort_order, session_name
        FROM template_day_sessions
        WHERE template_id IN ($placeholders)
        ORDER BY template_id, day_of_week, sort_order
    ");
    $sessionStmt->execute($templateIds);
    $sessions = $sessionStmt->fetchAll();

    $templateDays = [];
    foreach ($days as $day) {
        $templateDays[$day['template_id']][] = $day;
    }

    $templateSessions = [];
    foreach ($sessions as $session) {
        $templateSessions[$session['template_id']][] = $session;
    }

    $templateHolidays = [];
    foreach ($holidays as $holiday) {
        $templateHolidays[$holiday['template_id']][] = $holiday;
    }

    $templateStats = [];
    foreach ($statsRows as $row) {
        $templateStats[$row['template_id']] = $row;
    }

    foreach ($templates as &$template) {
        $template['days'] = $templateDays[$template['template_id']] ?? [];
        $template['sessions'] = $templateSessions[$template['template_id']] ?? [];
        $template['holidays'] = $templateHolidays[$template['template_id']] ?? [];
        $template['stats'] = $templateStats[$template['template_id']] ?? [
            'total_slots' => 0,
            'total_booked' => 0,
            'active_slots' => 0,
            'available_slots' => 0,
        ];
        // Always set month_stats when a month filter is provided, even if no slots exist yet.
        // This ensures the JS can properly display "This month" stats instead of falling back
        // to total stats while showing "This month" as the label.
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $template['month_stats'] = $monthStats[$template['template_id']] ?? [
                'total_slots' => 0,
                'total_booked' => 0,
                'active_slots' => 0,
                'available_slots' => 0,
            ];
        }
    }

    send_json($templates);
} catch (PDOException $e) {
    error_log('[HAMS] Error fetching schedule templates: ' . $e->getMessage());
    send_json(['error' => 'Failed to fetch schedule templates', 'detail' => $e->getMessage()], 500);
}