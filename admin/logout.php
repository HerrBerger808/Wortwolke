<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/bootstrap.php';

Auth::logout();
header('Location: /admin/login.php');
exit;
