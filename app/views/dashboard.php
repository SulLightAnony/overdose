<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Header & Quote -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold text-dark mb-1">Hellcome, <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>! 😛</h4>
            <p class="text-secondary small fst-italic" id="quote-container">
                <span class="placeholder-glow"><span class="placeholder col-6 opacity-50"></span></span>
            </p>
        </div>
    </div>

    <!-- 3 Kartu Statistik Warna Solid + Icon Putih -->
    <div class="row g-3 mb-4" id="stats-container">
        <!-- Selesai (Hijau Solid) -->
        <div class="col-4">
            <div class="stat-card stat-card-success">
                <div class="stat-card-body">
                    <div class="stat-icon-wrapper">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <h4 class="stat-number mb-0" id="stat-done"><span class="spinner-border spinner-border-sm"></span></h4>
                        <span class="stat-label">Selesai</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending (Kuning / Amber Solid) -->
        <div class="col-4">
            <div class="stat-card stat-card-warning">
                <div class="stat-card-body">
                    <div class="stat-icon-wrapper">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <h4 class="stat-number mb-0" id="stat-pending"><span class="spinner-border spinner-border-sm"></span></h4>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terlewat (Merah Solid) -->
        <div class="col-4">
            <div class="stat-card stat-card-danger">
                <div class="stat-card-body">
                    <div class="stat-icon-wrapper">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div>
                        <h4 class="stat-number mb-0" id="stat-missed"><span class="spinner-border spinner-border-sm"></span></h4>
                        <span class="stat-label">Terlewat</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Tugas & Top Contributor -->
    <div class="row g-4">
        <!-- Daftar Tugas -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3 fs-6">Daftar Tugas</h6>
                    <div id="pending-tasks-list" class="d-flex flex-column gap-2">
                        <!-- Skeleton Loaders -->
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3 opacity-50"></span></p>
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3 opacity-50"></span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboard (Top 3) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3 fs-6">Top Contributor</h6>
                    <div id="top-contributors-list" class="d-flex flex-column gap-3">
                        <!-- Skeleton Loaders -->
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3 opacity-50"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/dashboard.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>