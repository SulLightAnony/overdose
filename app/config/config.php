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
define('VAPID_PUBLIC_KEY', $_ENV['VAPID_PUBLIC_KEY'] ?? '');
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY'] ?? '');
define('VAPID_SUBJECT', $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@overdose.local');

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

// =========================================================================
// Konfigurasi Sesi PHP & Persistent Session (30 Hari Lifetime)
// =========================================================================
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 30 * 24 * 60 * 60); // 30 hari (2.592.000 detik)
}

if (!defined('SESSION_SECRET_KEY')) {
    define('SESSION_SECRET_KEY', $_ENV['APP_SECRET'] ?? GOOGLE_CLIENT_SECRET ?: 'Overdose_Secure_Session_Key_2026_Polban');
}

if (!function_exists('init_secure_session')) {
    function init_secure_session(): void {
        $cookieDuration = SESSION_LIFETIME;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        $cookiePath = '/';
        if (defined('BASE_URL')) {
            $parsedPath = parse_url(BASE_URL, PHP_URL_PATH);
            if (!empty($parsedPath)) {
                $cookiePath = rtrim($parsedPath, '/') . '/';
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string)$cookieDuration);
            session_set_cookie_params([
                'lifetime' => $cookieDuration,
                'path'     => $cookiePath,
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }


        // Perbarui cookie PHPSESSID secara eksplisit agar masa berlaku di browser konsisten (30 Hari)
        if (session_id() !== '') {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + $cookieDuration,
                'path'     => $cookiePath,
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
}

if (!function_exists('set_remember_cookie')) {
    function set_remember_cookie(array $user): void {
        $cookieDuration = SESSION_LIFETIME;
        $hash = hash_hmac('sha256', $user['userId'] . '|' . ($user['googleId'] ?? '') . '|' . ($user['emailAddress'] ?? ''), SESSION_SECRET_KEY);
        $cookieValue = $user['userId'] . '.' . $hash;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        $cookiePath = '/';
        if (defined('BASE_URL')) {
            $parsedPath = parse_url(BASE_URL, PHP_URL_PATH);
            if (!empty($parsedPath)) {
                $cookiePath = rtrim($parsedPath, '/') . '/';
            }
        }

        setcookie('overdose_remember', $cookieValue, [
            'expires'  => time() + $cookieDuration,
            'path'     => $cookiePath,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

if (!function_exists('clear_remember_cookie')) {
    function clear_remember_cookie(): void {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        $cookiePath = '/';
        if (defined('BASE_URL')) {
            $parsedPath = parse_url(BASE_URL, PHP_URL_PATH);
            if (!empty($parsedPath)) {
                $cookiePath = rtrim($parsedPath, '/') . '/';
            }
        }
        setcookie('overdose_remember', '', [
            'expires'  => time() - 3600,
            'path'     => $cookiePath,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        setcookie('overdose_remember', '', time() - 3600, '/');
    }
}

if (!function_exists('try_restore_session_from_cookie')) {
    function try_restore_session_from_cookie(?PDO $pdo = null): bool {
        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        if (empty($_COOKIE['overdose_remember'])) {
            return false;
        }

        $cookieVal = $_COOKIE['overdose_remember'];
        $parts = explode('.', $cookieVal, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $userId = (int)$parts[0];
        $tokenHash = $parts[1];

        if ($userId <= 0 || empty($tokenHash)) {
            return false;
        }

        if ($pdo === null) {
            global $pdo;
        }

        if (!($pdo instanceof PDO)) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (Throwable $e) {
                return false;
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE userId = :userId LIMIT 1');
            $stmt->execute(['userId' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                clear_remember_cookie();
                return false;
            }

            if (($user['userStatus'] ?? 'active') === 'blocked') {
                clear_remember_cookie();
                return false;
            }

            $expectedHash = hash_hmac('sha256', $user['userId'] . '|' . ($user['googleId'] ?? '') . '|' . ($user['emailAddress'] ?? ''), SESSION_SECRET_KEY);
            if (!hash_equals($expectedHash, $tokenHash)) {
                clear_remember_cookie();
                return false;
            }

            $hasOnboarded = !empty($user['majorType']) && !empty($user['studyProgram']) && !empty($user['classGroup']);

            $_SESSION['user_id']                 = $user['userId'];
            $_SESSION['user_name']               = $user['userName'];
            $_SESSION['email_address']           = $user['emailAddress'];
            $_SESSION['role_level']              = $user['roleLevel'];
            $_SESSION['avatar_url']              = $user['avatarUrl'];
            $_SESSION['major_type']              = $user['majorType'];
            $_SESSION['study_program']           = $user['studyProgram'];
            $_SESSION['class_group']             = $user['classGroup'];
            $_SESSION['batch_year']              = $user['batchYear'];
            $_SESSION['hide_completed_identity'] = $user['isAnonymous'] ?? $user['hideCompletedIdentity'] ?? 0;
            $_SESSION['onboarding_completed']    = $hasOnboarded;

            set_remember_cookie($user);
            return true;
        } catch (Throwable $e) {
            error_log('Error restoring session from cookie: ' . $e->getMessage());
            return false;
        }
    }
}

// Inisialisasi otomatis sesi & restore dari cookie jika sesi kosong
init_secure_session();
try_restore_session_from_cookie();