<?php
// Mencegah redirect loop dari reverse proxy Railway
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'petugas') {
        header("Location: /petugas/dashboard.php");
    } else {
        header("Location: /mahasiswa/dashboard.php");
    }
    exit;
}

// Arahkan dengan path absolut
header("Location: /public/dashboard.php", true, 302);
exit;
?>
