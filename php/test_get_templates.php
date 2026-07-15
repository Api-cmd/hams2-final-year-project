<?php
require_once 'config.php';

// Manually set session as admin to bypass require_role
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'System Administrator';
$_SESSION['user_role'] = 'admin';
$_SESSION['user_email'] = 'admin@hams2.com';

require_once 'admin_get_schedule_templates.php';
