<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

// 1. Kosongkan array sesi
$_SESSION = array();

// 2. Hapus cookie sesi PHP di browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hapus cookie remember-me jika ada
if (isset($_COOKIE['overdose_remember'])) {
    setcookie('overdose_remember', '', time() - 3600, '/');
}

// 4. Hancurkan sesi di server
session_destroy();

// 5. Redireksi ke halaman login
header('Location: ' . BASE_URL . 'login');
exit;