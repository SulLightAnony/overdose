<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Penanganan Pesan Error Sesuai Parameter URL
$errorCode = $_GET['error'] ?? null;
$modalTitle = '';
$modalMessage = '';

if ($errorCode) {
    switch ($errorCode) {
        case 'invalid_domain':
            $modalTitle = 'Login Gagal';
            $modalMessage = 'Proses gagal. Gunakan email Polban (@polban.ac.id) untuk login.';
            break;
        case 'blacklisted':
            $modalTitle = 'Akses Ditolak';
            $modalMessage = 'Terdapat sesuatu yang salah dengan akunmu.';
            break;
        default:
            $modalTitle = 'Terjadi Kesalahan';
            $modalMessage = 'Terjadi kesalahan tidak terduga saat mencoba login. Silakan coba lagi nanti.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdose — Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/logo.png" type="image/png">
</head>
<body class="login-body">

    <div class="login-container">
        
        <!-- Logo Title -->
        <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="brand-logo" onerror="this.style.display='none'; document.getElementById('alt-title').style.display='block';">
        <h1 id="alt-title" class="fw-bold text-dark mb-4 fs-3" style="display: none;">OVERDOSE</h1>

        <!-- Button Google Login dengan Efek Stroke Melingkar -->
        <a href="<?= BASE_URL ?>app/api/auth/google_redirect.php" class="btn-google">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Login with Google <span style="font-size: 0.8rem;">(@polban.ac.id)</span></span>
        </a>

        <!-- Teks Our Sanctuary -->
        <div class="sanctuary-text">
            "Our Sanctuary" — an app built exclusively<br>for students.
        </div>

        <!-- Footer Made by Almusayid -->
        <div class="creator-footer">
            Made with frustration❤ by Almusayid.
        </div>

        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" class="wave-svg"><path fill="#273036" fill-opacity="1" d="M0,32L24,80C48,128,96,224,144,250.7C192,277,240,235,288,218.7C336,203,384,213,432,186.7C480,160,528,96,576,69.3C624,43,672,53,720,74.7C768,96,816,128,864,144C912,160,960,160,1008,138.7C1056,117,1104,75,1152,80C1200,85,1248,139,1296,138.7C1344,139,1392,85,1416,58.7L1440,32L1440,320L1416,320C1392,320,1344,320,1296,320C1248,320,1200,320,1152,320C1104,320,1056,320,1008,320C960,320,912,320,864,320C816,320,768,320,720,320C672,320,624,320,576,320C528,320,480,320,432,320C384,320,336,320,288,320C240,320,192,320,144,320C96,320,48,320,24,320L0,320Z"></path></svg>
    </div>

    <!-- Modal Error Login -->
    <?php if ($errorCode): ?>
    <div class="modal fade" id="loginErrorModal" tabindex="-1" aria-labelledby="loginErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content text-center border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body p-4">
                    <div class="mb-3 text-danger fs-1">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-2 text-dark" id="loginErrorModalLabel"><?= htmlspecialchars($modalTitle) ?></h5>
                    <p class="text-secondary small mb-4"><?= htmlspecialchars($modalMessage) ?></p>
                    <button type="button" class="btn btn-dark w-100 rounded-3 py-2 fw-semibold" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Global JS Variables -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Main Client-Side JS -->
    <script src="<?= BASE_URL ?>public/js/app.js"></script>

    <?php if ($errorCode): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var modalEl = document.getElementById('loginErrorModal');
            if (modalEl) {
                var errorModal = new bootstrap.Modal(modalEl);
                errorModal.show();
                window.history.replaceState({}, document.title, window.location.origin + window.location.pathname);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>