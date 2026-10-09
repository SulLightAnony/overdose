<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$filter = $_GET['filter'] ?? 'all';
$pageTitle = 'Daftar Tugas';
$activeMenu = 'tasks';
ob_start();
?>
<div class="container-fluid p-0">
    <!-- Header Halaman -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-card-checklist me-2"></i>Daftar Tugas
            </h4>
            <p class="text-secondary small mb-0">Tugas gabungan dari seluruh mata kuliah semester aktif.</p>
        </div>
    </div>

    <!-- Banner Semester Aktif -->
    <div id="activeSemesterBanner" class="card border-0 shadow-sm rounded-4 mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6) !important;">
        <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-2 fw-medium">Semester Aktif</span>
                <h5 class="fw-bold mb-1" id="tasksActiveSemesterTitle">Memuat Semester...</h5>
                <small class="text-white-50" id="tasksActiveSemesterInfo">Program Studi & Angkatan</small>
            </div>
            <div class="text-end d-none d-md-block">
                <i class="bi bi-card-checklist display-4 text-white opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- 4 Filter Tugas (Semua, Pending, Selesai, Terlewat) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2 p-md-3">
            <div class="d-flex flex-wrap gap-2" id="taskFiltersContainer">
                <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold task-filter-btn" data-filter="all">
                    <i class="bi bi-layers-fill me-1"></i> Semua Tugas
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold task-filter-btn" data-filter="pending">
                    <i class="bi bi-hourglass-split me-1"></i> Pending
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold task-filter-btn" data-filter="done">
                    <i class="bi bi-check-circle-fill me-1"></i> Selesai
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold task-filter-btn" data-filter="missed">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Terlewat
                </button>
            </div>
        </div>
    </div>

    <div id="globalTasksAlert"></div>
    <div id="globalTasksList" class="d-flex flex-column gap-3">
        <div class="text-center text-muted py-5">
            <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
            Memuat tugas...
        </div>
    </div>
</div>

<style>
    .task-card-item {
        cursor: pointer;
        transition: transform 160ms ease, box-shadow 160ms ease;
    }
    .task-card-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1) !important;
    }
    .hover-primary {
        transition: color 150ms ease;
    }
    .hover-primary:hover {
        color: #0d6efd !important;
        text-decoration: underline !important;
    }
</style>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    let GLOBAL_TASK_FILTER = <?= json_encode($filter) ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/tasks.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
