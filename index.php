<?php
// index.php — Entry point
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

$baseUrl = rtrim(getenv('APP_URL'), '/');


if (isLoggedIn()) {
    header('Location: ' . $baseUrl . '/pages/dashboard.php');
} else {
    header('Location: ' . $baseUrl . '/pages/login.php');
}
exit;
