<?php
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';

Auth::logout();
header('Location: /admin/login.php');
exit;
