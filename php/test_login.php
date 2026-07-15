<?php
require_once 'config.php';

// Set a test admin session!
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'System Administrator';
$_SESSION['user_role'] = 'admin';
$_SESSION['user_email'] = 'admin@hams2.com';

header('Location: /hams2/pages/admin-schedules.html');
exit;
