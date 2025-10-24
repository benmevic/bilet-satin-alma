<?php
session_start();

// Session'ı temizle
session_unset();
session_destroy();

// Cookie varsa temizle
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Login sayfasına yönlendir
header('Location: login.php?logout=success');
exit();
?>