<?php
// index.php — Entry point
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
} else {
    header('Location: /pages/login.php');
}
exit;
