<?php
// Gatekeeper Keamanan: Pengecekan otomatis di setiap halaman terproteksi
require_once __DIR__ . '/../api/auth/gatekeeper.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

// Data pengguna & notifikasi dari Sesi
$userName           = $_SESSION['user_name'] ?? 'Pengguna';
$userAvatar         = $_SESSION['user_avatar'] ?? BASE_URL . 'public/assets/img/logo.png';
$hasNotification    = $_SESSION['has_unread_notification'] ?? false; // Ubah nilai ini jika ada notifikasi
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

    <!-- NAVBAR KIRI / SIDEBAR (Responsive Offcanvas) -->
    <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
        
        <!-- Header Sidebar (Logo) -->
        <div class="offcanvas-header d-flex align-items-center justify-content-between p-3 border-bottom border-secondary border-opacity-25">
            <a href="<?= BASE_URL ?>dashboard" class="d-flex align-items-center text-decoration-none">
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="sidebar-brand-logo">
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>

        <div class="d-none d-lg-block p-3 text-center border-bottom border-secondary border-opacity-25">
            <a href="<?= BASE_URL ?>dashboard">
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="sidebar-brand-logo">
            </a>
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
                    <a href="<?= BASE_URL ?>settings" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'settings') ? 'active' : '' ?>">
                        <i class="bi bi-gear-fill"></i>
                        <span>Pengaturan Profil</span>
                    </a>
                </li>
            </ul>

            <!-- Tombol Keluar / Logout -->
            <div class="pt-3 border-top border-secondary border-opacity-25">
                <a href="<?= BASE_URL ?>logout" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- AREA UTAMA (TOPBAR + CONTENT) -->
    <div class="app-main">
        
        <!-- TOPBAR -->
        <header class="app-topbar">
            <!-- Left Side: Hamburger Trigger untuk Mobile -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-nav-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>

            <!-- Right Side: Notifikasi & Profil -->
            <div class="d-flex align-items-center gap-2">
                
                <!-- Ikon Lonceng Notifikasi -->
                <button type="button" class="btn btn-nav-icon" title="Notifikasi" id="notificationBtn">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($hasNotification): ?>
                        <span class="red-dot-indicator"></span>
                    <?php endif; ?>
                </button>

                <!-- Avatar Profil Pengguna -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar-circle">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-semibold text-dark small"><?= htmlspecialchars($userName) ?></p>
                        </li>
                        <li>
                            <a class="dropdown-menu-item dropdown-item small py-2" href="<?= BASE_URL ?>settings">
                                <i class="bi bi-person me-2"></i> Pengaturan Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-menu-item dropdown-item small py-2 text-danger" href="<?= BASE_URL ?>logout">
                                <i class="bi bi-box-arrow-left me-2"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </header>

        <!-- MAIN CONTENT AREA (Dynamic Wrapper) -->
        <main id="app-content">
            <?php 
            // Tempat injecting/rendering konten halaman
            if (isset($viewContentPath) && file_exists($viewContentPath)) {
                require_once $viewContentPath;
            }
            ?>
        </main>

    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>