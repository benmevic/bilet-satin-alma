<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit();
}

// Company ID kontrolü
if (empty($_SESSION['company_id'])) {
    session_destroy();
    header('Location: login.php?error=no_company');
    exit();
}
?>