<?php
// Pastikan BASE_URL terdefinisi
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

// Jika user sudah login, langsung lempar ke dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdose — Our Sanctuary</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
</head>
<body class="login-body">

    <div class="container d-flex justify-content-center align-items-center">
        <div class="login-card text-center">
            
            <!-- Logo Title -->
            <div class="mb-3">
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="brand-logo">
                <h2 id="alt-title" class="fw-bold text-white mb-0" style="display: none;">OVERDOSE</h2>
            </div>

            <!-- Tagline Badge -->
            <div class="mb-4">
                <span class="tagline-badge">
                    <i class="bi bi-shield-lock-fill me-1"></i> Our Sanctuary
                </span>
            </div>

            <p class="text-secondary small mb-4">
                Pusat penjadwalan & manajemen tugas terpadu eksklusif untuk Mahasiswa Politeknik Negeri Bandung.
            </p>

            <!-- Alert Notifikasi Error -->
            <?php if ($errorMessage): ?>
                <div class="alert alert-custom-danger d-flex align-items-center text-start p-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?= $errorMessage ?></div>
                </div>
            <?php endif; ?>

            <!-- Single OAuth Login Button -->
            <div class="d-grid mb-4">
                <a href="<?= BASE_URL ?>api/auth/google" class="btn-google">
                    <!-- Google SVG Icon -->
                    <svg class="google-icon" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Login with Google (@polban.ac.id)</span>
                </a>
            </div>

            <!-- Footer Domain Restriction Notice -->
            <div class="login-footer-text">
                <i class="bi bi-info-circle me-1"></i> Akses dibatasi untuk domain email resmi <strong>@polban.ac.id</strong>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>