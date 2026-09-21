<?php
// Parser .env dengan penanganan tanda kutip dan komentar inline
$envPath = __DIR__ . '/../../.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/../.env';
}

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, ' #')) $line = explode(' #', $line, 2)[0];
        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim(trim($value), "\"'");
        }
    }
}

$online = filter_var($_ENV['ONLINE'] ?? false, FILTER_VALIDATE_BOOLEAN);
date_default_timezone_set('Asia/Jakarta');

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Deteksi Protokol & Host Otomatis (Anti Mixed Content)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($online) {
    define('BASE_URL', 'https://overdose.moboidgroup.com/');
    define('GOOGLE_REDIRECT_URI', 'https://overdose.moboidgroup.com/app/api/auth/google_callback.php');
} else {
    // BASE_URL mengarah ke Root Proyek (Bukan ke folder app/)
    define('BASE_URL', $protocol . $host . '/_Projects_/P020-Overdose/');
    define('GOOGLE_REDIRECT_URI', $protocol . $host . '/_Projects_/P020-Overdose/app/api/auth/google_callback.php');
}

define('ALLOWED_EMAIL_DOMAIN', '@polban.ac.id');

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

if ($online) {
    define('DB_NAME', $_ENV['DB_NAME_ONLINE'] ?? '');
    define('DB_USER', $_ENV['DB_USER_ONLINE'] ?? '');
    define('DB_PASS', $_ENV['DB_PASS_ONLINE'] ?? '');
} else {
    define('DB_NAME', $_ENV['DB_NAME_LOCAL'] ?? '');
    define('DB_USER', $_ENV['DB_USER_LOCAL'] ?? '');
    define('DB_PASS', $_ENV['DB_PASS_LOCAL'] ?? '');
}