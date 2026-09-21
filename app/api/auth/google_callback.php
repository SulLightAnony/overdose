<?php
// Konfigurasi durasi Cookie Sesi hingga 30 hari (2.592.000 detik)
$cookieDuration = 30 * 24 * 60 * 60;

if (session_status() === PHP_SESSION_NONE) {
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

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

// =========================================================================
// VALIDASI 1: Cek State CSRF Token & Terima Callback / Authorization Code
// =========================================================================
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    unset($_SESSION['oauth_state']);
    header('Location: ' . BASE_URL . 'login?error=' . urlencode('Sesi autentikasi tidak valid (CSRF Mismatch).'));
    exit;
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    header('Location: ' . BASE_URL . 'login?error=' . urlencode('Otorisasi Google dibatalkan atau gagal.'));
    exit;
}

$code = $_GET['code'];

// Tukar Code dengan Access Token dari Google
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header('Location: ' . BASE_URL . 'login?error=' . urlencode('Gagal mendapatkan token dari Google.'));
    exit;
}

// Ambil Profil User dari Google API
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token']
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$userProfileResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userProfileResponse, true);
$email     = strtolower($googleUser['email'] ?? '');
$googleId  = $googleUser['sub'] ?? '';
$fullName  = $googleUser['name'] ?? '';
$picture   = $googleUser['picture'] ?? '';

// =========================================================================
// VALIDASI 2 (Domain Check): Cek email wajib berakhiran @polban.ac.id
// =========================================================================
if (!str_ends_with($email, ALLOWED_EMAIL_DOMAIN)) {
    header('Location: ' . BASE_URL . 'login?error=' . urlencode('Akses khusus mahasiswa Polban (@polban.ac.id).'));
    exit;
}

// =========================================================================
// VALIDASI 3 (Blacklist Check): Cek apakah email terdaftar di emailBlacklists
// =========================================================================
$stmt = $pdo->prepare("SELECT blacklistId FROM emailBlacklists WHERE LOWER(emailAddress) = :email");
$stmt->execute(['email' => $email]);

if ($stmt->fetch()) {
    header('Location: ' . BASE_URL . 'login?error=' . urlencode('Gagal login. Terdapat masalah dengan akunmu.'));
    exit;
}

// =========================================================================
// VALIDASI 4 (User Handling): Pendaftaran User Baru vs Penanganan User Lama
// =========================================================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(emailAddress) = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

$isNewUser = false;

if (!$user) {
    // ---- KASUS EMAIL BARU ----
    $isNewUser = true;
    
    $insertStmt = $pdo->prepare("
        INSERT INTO users (googleId, userName, emailAddress, avatarUrl, roleLevel)
        VALUES (:googleId, :userName, :emailAddress, :avatarUrl, 'member')
    ");
    $insertStmt->execute([
        'googleId'     => $googleId,
        'userName'     => $fullName,
        'emailAddress' => $email,
        'avatarUrl'    => $picture
    ]);

    // Ambil data user yang baru disimpan
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(emailAddress) = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
} else {
    // ---- KASUS EMAIL LAMA ----
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET googleId = :googleId, userName = :userName, avatarUrl = :avatarUrl 
        WHERE userId = :userId
    ");
    $updateStmt->execute([
        'googleId'  => $googleId,
        'userName'  => $fullName,
        'avatarUrl' => $picture,
        'userId'    => $user['userId']
    ]);
}

// Evaluasi status onboarding (jika data jurusan/prodi masih kosong)
$hasOnboarded = !empty($user['majorType']) && !empty($user['studyProgram']) && !empty($user['classGroup']);
$onboardingCompleted = !$isNewUser && $hasOnboarded;

// Set Session Long-Lived
$_SESSION['user_id']                 = $user['userId'];
$_SESSION['user_name']               = $user['userName'];
$_SESSION['email_address']           = $user['emailAddress'];
$_SESSION['role_level']              = $user['roleLevel'];
$_SESSION['avatar_url']              = $picture;
$_SESSION['major_type']              = $user['majorType'];
$_SESSION['study_program']           = $user['studyProgram'];
$_SESSION['class_group']             = $user['classGroup'];
$_SESSION['batch_year']              = $user['batchYear'];
$_SESSION['hide_completed_identity'] = $user['hideCompletedIdentity'] ?? 0;
$_SESSION['onboarding_completed']    = $onboardingCompleted;

// Set Persistent Cookie (30 Hari)
setcookie('overdose_remember', session_id(), [
    'expires'  => time() + $cookieDuration,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Arahkan Pengguna
if (!$onboardingCompleted) {
    header('Location: ' . BASE_URL . 'settings?onboarding=true');
} else {
    header('Location: ' . BASE_URL . 'dashboard');
}
exit;