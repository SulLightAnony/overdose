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
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        // Hapus komentar inline jika ada
        if (str_contains($line, ' #')) {
            $line = explode(' #', $line, 2)[0];
        }
        
        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), "\"'"); // Hapus tanda kutip tunggal/ganda
            $_ENV[$name] = $value;
        }
    }
}

$online = filter_var($_ENV['ONLINE'] ?? false, FILTER_VALIDATE_BOOLEAN);

date_default_timezone_set('Asia/Jakarta');

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Deteksi protokol otomatis (http / https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($online) {
    define('BASE_URL', 'https://overdose.moboidgroup.com/app/');
    define('GOOGLE_REDIRECT_URI', 'https://overdose.moboidgroup.com/app/api/auth/google_callback.php');
} else {
    define('BASE_URL', 'http://localhost/_projects_/P020-Overdose/app/');
    define('GOOGLE_REDIRECT_URI', 'http://localhost/_projects_/P020-Overdose/app/api/auth/google_callback.php');
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