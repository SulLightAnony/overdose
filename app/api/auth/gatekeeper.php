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

$isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

$denyRequest = static function (int $statusCode, string $message) use ($isApiRequest): void {
    session_unset();
    session_destroy();
    http_response_code($statusCode);

    if ($isApiRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => null
        ]);
    } else {
        header('Location: ' . BASE_URL . 'login');
    }
    exit;
};

// 1. Cek validitas sesi user secara instan dari memori
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $denyRequest(401, 'Akses ditolak. Silakan login terlebih dahulu.');
}

// 2. Validasi keberadaan dan status akun pada setiap request yang dilindungi.
require_once __DIR__ . '/../db.php';
try {
    $stmtUser = $pdo->prepare('SELECT userStatus, roleLevel FROM users WHERE userId = :userId LIMIT 1');
    $stmtUser->execute(['userId' => (int)$_SESSION['user_id']]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Gatekeeper database error: ' . $exception->getMessage());
    $denyRequest(500, 'Layanan database sedang tidak tersedia.');
}

if (!$currentUser) {
    $denyRequest(401, 'Sesi tidak valid atau pengguna telah dihapus.');
}

if ($currentUser['userStatus'] === 'blocked') {
    $denyRequest(403, 'Akun Anda telah diblokir.');
}

// Jangan memakai role lama dari session setelah perubahan role di database.
$_SESSION['role_level'] = $currentUser['roleLevel'];

// 3. Ambil path URL saat ini untuk mendeteksi halaman yang sedang diakses
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$isOnboardingPage = str_contains($currentPath, 'settings') || str_contains($currentPath, 'configuration');

// 4. Cek status kelengkapan onboarding
$onboardingCompleted = $_SESSION['onboarding_completed'] ?? false;

// Jika belum onboarding dan mencoba mengakses halaman privat selain configurations, lempar ke configurations.
if (!$onboardingCompleted && !$isOnboardingPage) {
    header('Location: ' . BASE_URL . 'configurations?onboarding=true');
    exit;
}