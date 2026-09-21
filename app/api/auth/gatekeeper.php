<?php
if (session_status() === PHP_SESSION_NONE) {
    $cookieDuration = 30 * 24 * 60 * 60;
    ini_set('session.gc_maxlifetime', $cookieDuration);
    session_set_cookie_params([
        'lifetime' => $cookieDuration,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

// Cek validitas sesi user secara instan dari memori
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'app/api/auth/logout.php');
    exit;
}