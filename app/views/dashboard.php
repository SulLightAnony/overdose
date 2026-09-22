<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Header, Quote, & Navigasi Semester -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8 col-12 mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">Hellcome, <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>! 😛</h4>            
        </div>
        <div class="col-md-4 col-12 text-md-end">
            <a href="<?= BASE_URL ?>semester" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold">
                <i class="bi bi-journal-bookmark-fill me-1"></i> Ke Halaman Semester
            </a>
        </div>
    </div>

    <!-- 3 Kartu Statistik Berupa Href Filter -->
    <div class="row g-2 g-md-3 mb-4" id="stats-container">
        <!-- Selesai -->
        <div class="col-4">
            <a href="<?= BASE_URL ?>tasks?filter=done" class="text-decoration-none d-block">
                <div class="stat-card stat-card-success">
                    <div class="stat-card-body">
                        <div class="stat-icon-wrapper"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-number mb-0" id="stat-done"><span class="spinner-border spinner-border-sm"></span></h4>
                            <span class="stat-label">Selesai</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Pending -->
        <div class="col-4">
            <a href="<?= BASE_URL ?>tasks?filter=pending" class="text-decoration-none d-block">
                <div class="stat-card stat-card-warning">
                    <div class="stat-card-body">
                        <div class="stat-icon-wrapper"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-number mb-0" id="stat-pending"><span class="spinner-border spinner-border-sm"></span></h4>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Terlewat -->
        <div class="col-4">
            <a href="<?= BASE_URL ?>tasks?filter=missed" class="text-decoration-none d-block">
                <div class="stat-card stat-card-danger">
                    <div class="stat-card-body">
                        <div class="stat-icon-wrapper"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="stat-info">
                            <h4 class="stat-number mb-0" id="stat-missed"><span class="spinner-border spinner-border-sm"></span></h4>
                            <span class="stat-label">Terlewat</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Daftar Tugas & Top Contributor -->
    <div class="row g-4">
        <!-- Daftar Tugas -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column justify-content-between">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-dark mb-3 fs-6">Daftar Tugas</h6>
                    <div id="pending-tasks-list" class="d-flex flex-column gap-2">
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3"></span></p>
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3"></span></p>
                    </div>
                </div>
                <!-- Tombol Tampilkan Semua Tugas -->
                <div class="card-footer bg-transparent border-top p-3 text-center">
                    <a href="<?= BASE_URL ?>tasks" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold">
                        Tampilkan Semua Tugas <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Leaderboard / Top Contributor -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-dark mb-3 fs-6">Top Contributor</h6>
                    <div id="top-contributors-list" class="d-flex flex-column gap-3">
                        <p class="placeholder-glow mb-0"><span class="placeholder col-12 py-3 rounded-3"></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-bold text-dark mb-2 fs-6">Random Quote:</h6>
                <p class="text-secondary small fst-italic mb-0" id="quote-container">
                    <span class="placeholder-glow"><span class="placeholder col-8 col-md-6"></span></span>
                </p>
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