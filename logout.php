<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Auth.php';

Auth::logout();

header('Location: admin_login.php');
exit;