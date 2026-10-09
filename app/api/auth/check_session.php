<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

init_secure_session();
try_restore_session_from_cookie($pdo);

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {

    try {
        // Cek apakah user masih ada di database
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE userId = :userId LIMIT 1");
        $stmt->execute(['userId' => $_SESSION['user_id']]);
        $userExists = $stmt->fetchColumn();

        if ($userExists) {
            jsonResponse(true, 'Sesi aktif dan valid.', [
                'userId'              => $_SESSION['user_id'],
                'onboardingCompleted' => $_SESSION['onboarding_completed'] ?? false
            ]);
            exit;
        }
    } catch (PDOException $e) {
        // Abaikan dan jalankan respon tidak valid di bawah
    }
}

// Jika user dihapus dari DB atau sesi habis
jsonResponse(false, 'Sesi tidak valid atau pengguna telah dihapus.', null, 200);