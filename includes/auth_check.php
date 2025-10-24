<?php
// includes/auth_check.php
// Giriş kontrolü - Her korumalı sayfada include edilecek

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>