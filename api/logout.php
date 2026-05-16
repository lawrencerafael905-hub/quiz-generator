<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
logoutUser();
header('Location: ../pages/login.php');
exit;
