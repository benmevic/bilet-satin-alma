<?php
session_start();
session_destroy();

// Tüm session verilerini temizle
$_SESSION = array();

// Session cookie'sini de sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Admin login sayfasına yönlendir
header('Location: login.php');
exit();