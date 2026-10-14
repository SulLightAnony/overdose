<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

init_secure_session();
try_restore_session_from_cookie($pdo);

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {

    try {
        $stmt = $pdo->prepare("SELECT u.userId, u.userStatus, b.blacklistId 
                               FROM users u 
                               LEFT JOIN emailblacklists b ON LOWER(u.emailAddress) = LOWER(b.emailAddress)
                               WHERE u.userId = :userId LIMIT 1");
        $stmt->execute(['userId' => (int)$_SESSION['user_id']]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow && $userRow['userStatus'] !== 'blocked' && empty($userRow['blacklistId'])) {
            jsonResponse(true, 'Sesi aktif dan valid.', [
                'userId'              => $_SESSION['user_id'],
                'onboardingCompleted' => $_SESSION['onboarding_completed'] ?? false
            ]);
            exit;
        } else {
            session_unset();
            session_destroy();
            jsonResponse(false, 'Terjadi kesalahan saat memproses permintaan Anda. Silakan coba beberapa saat lagi.', null, 200);
            exit;
        }
    } catch (PDOException $e) {
        // Abaikan dan jalankan respon tidak valid di bawah
    }
}

// Jika user dihapus dari DB atau sesi habis
jsonResponse(false, 'Terjadi kesalahan saat memproses permintaan Anda. Silakan coba beberapa saat lagi.', null, 200);