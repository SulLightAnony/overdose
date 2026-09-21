<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (!isset($_GET['code'])) {
    header('Location: ' . BASE_URL . 'login?error=missing_code');
    exit;
}

$code = $_GET['code'];

// 1. Tukar Code dengan Access Token dari Google
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
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header('Location: ' . BASE_URL . 'login?error=invalid_token');
    exit;
}

// 2. Ambil Profil User dari Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token']
]);
$userProfileResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userProfileResponse, true);
$email = strtolower($googleUser['email'] ?? '');
$googleId = $googleUser['sub'] ?? '';
$fullName = $googleUser['name'] ?? '';
$picture = $googleUser['picture'] ?? '';

// 3. Validasi Domain Email @polban.ac.id
if (!str_ends_with($email, ALLOWED_EMAIL_DOMAIN)) {
    header('Location: ' . BASE_URL . 'login?error=invalid_domain');
    exit;
}

// 4. Cek Tabel Blacklist
$stmt = $pdo->prepare("SELECT blacklistId FROM emailBlacklists WHERE emailAddress = :email");
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    header('Location: ' . BASE_URL . 'login?error=blacklisted');
    exit;
}

// 5. Cek atau Buat User Baru di Tabel users
$stmt = $pdo->prepare("SELECT * FROM users WHERE emailAddress = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if ($user) {
    // User sudah ada -> Update googleId & avatar jika belum terikat
    $updateStmt = $pdo->prepare("UPDATE users SET googleId = :googleId, avatarUrl = :avatarUrl WHERE userId = :userId");
    $updateStmt->execute([
        'googleId'  => $googleId,
        'avatarUrl' => $picture,
        'userId'    => $user['userId']
    ]);
} else {
    // User baru -> Regitrasi otomatis dengan roleLevel default 'member'
    $insertStmt = $pdo->prepare("
        INSERT INTO users (googleId, userName, emailAddress, avatarUrl, roleLevel, majorType, studyProgram, classGroup, batchYear)
        VALUES (:googleId, :userName, :emailAddress, :avatarUrl, 'member', 'D4', 'Teknik Informatika', 'A', 2025)
    ");
    $insertStmt->execute([
        'googleId'     => $googleId,
        'userName'     => $fullName,
        'emailAddress' => $email,
        'avatarUrl'    => $picture
    ]);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE emailAddress = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
}

// 6. Simpan Data Ke Sesi
$_SESSION['user_id']                 = $user['userId'];
$_SESSION['user_name']               = $user['userName'];
$_SESSION['email_address']           = $user['emailAddress'];
$_SESSION['role_level']              = $user['roleLevel'];
$_SESSION['avatar_url']              = $picture;
$_SESSION['major_type']              = $user['majorType'];
$_SESSION['study_program']           = $user['studyProgram'];
$_SESSION['class_group']             = $user['classGroup'];
$_SESSION['batch_year']              = $user['batchYear'];
$_SESSION['hide_completed_identity'] = $user['hideCompletedIdentity'];

header('Location: ' . BASE_URL . 'dashboard');
exit;