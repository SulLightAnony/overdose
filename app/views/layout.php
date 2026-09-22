<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../api/db.php';

// Pastikan data profil & avatar tersinkron dari DB (menggunakan userName, bukan name)
if (isset($_SESSION['user_id'])) {
    $stmtNavUser = $pdo->prepare("SELECT userName, avatarUrl FROM users WHERE userId = :userId LIMIT 1");
    $stmtNavUser->execute(['userId' => $_SESSION['user_id']]);
    $navUserData = $stmtNavUser->fetch(PDO::FETCH_ASSOC);
    
    if ($navUserData) {
        $userName   = $navUserData['userName'];
        $userAvatar = !empty($navUserData['avatarUrl']) ? $navUserData['avatarUrl'] : BASE_URL . 'public/assets/img/logo.png';
    }
}

$userName        = $userName ?? ($_SESSION['user_name'] ?? 'Pengguna');
$userAvatar      = $userAvatar ?? ($_SESSION['user_avatar'] ?? BASE_URL . 'public/assets/img/logo.png');
$hasNotification = $_SESSION['has_unread_notification'] ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Overdose' : 'Overdose' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/logo.png" type="image/png">
</head>
<body>

<div class="app-wrapper">

    <!-- NAVBAR KIRI / SIDEBAR -->
    <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
        
        <!-- Single Brand Logo Top Header -->
        <div class="sidebar-header p-3 d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-25">
            <a href="<?= BASE_URL ?>dashboard" class="d-flex align-items-center text-decoration-none">
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="sidebar-brand-logo">
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>

        <!-- Menu Navigasi -->
        <div class="offcanvas-body d-flex flex-column justify-content-between p-3">
            <ul class="nav nav-pills flex-column gap-1 w-100">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>dashboard" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>semester" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'semester') ? 'active' : '' ?>">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span>Semester & Matkul</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>configuration" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'settings') ? 'active' : '' ?>">
                        <i class="bi bi-gear-fill"></i>
                        <span>Pengaturan</span>
                    </a>
                </li>
            </ul>

            <!-- Tombol Keluar (Memicu Modal) -->
            <div class="pt-3 border-top border-secondary border-opacity-25">
                <button type="button" class="nav-link text-danger w-100 text-start bg-transparent border-0" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Keluar</span>
                </button>
            </div>
        </div>
    </aside>

    <!-- AREA UTAMA -->
    <div class="app-main">
        
        <!-- TOPBAR -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-nav-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Ikon Lonceng Notifikasi (HREF ke /notifications) -->
                <a href="<?= BASE_URL ?>notifications" class="btn btn-nav-icon position-relative text-decoration-none" title="Notifikasi">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($hasNotification): ?>
                        <span class="red-dot-indicator"></span>
                    <?php endif; ?>
                </a>

                <!-- Avatar Profil Pengguna Google -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar-circle" referrerpolicy="no-referrer">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-semibold text-dark small"><?= htmlspecialchars($userName) ?></p>
                        </li>
                        <li>
                            <a class="dropdown-item small py-2" href="<?= BASE_URL ?>configuration">
                                <i class="bi bi-person me-2"></i> Pengaturan Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item small py-2 text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="bi bi-box-arrow-left me-2"></i> Keluar
                            </button>
                        </li>
                    </ul>
                </div>

            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main id="app-content">
            <?php 
            if (isset($viewContentPath) && file_exists($viewContentPath)) {
                require_once $viewContentPath;
            }
            echo $content ?? ''; 
            ?>
        </main>

    </div>

</div>

<!-- MODAL KONFIRMASI LOGOUT -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4 p-2 text-center">
            <div class="modal-body">
                <i class="bi bi-exclamation-circle text-warning fs-1 mb-2 d-block"></i>
                <h6 class="fw-bold text-dark mb-1">Konfirmasi Keluar</h6>
                <p class="text-secondary small mb-4">Apakah kamu yakin ingin keluar dari akun ini?</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border w-50 py-2 rounded-3 fw-semibold small" data-bs-dismiss="modal">Batal</button>
                    <a href="<?= BASE_URL ?>logout" class="btn btn-danger w-50 py-2 rounded-3 fw-semibold small text-decoration-none">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>