<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Jadwal Perkuliahan';
$activeMenu = 'schedule';
ob_start();
?>
<div class="container-fluid p-0" id="schedule-container">
    <div id="scheduleExportArea" aria-hidden="true" style="position:absolute; left:-9999px; top:0; width:794px; padding:32px 36px; background:#fff; color:#111; font-family:Arial,Helvetica,sans-serif; overflow:visible;"></div>
    <!-- Header Halaman & Tombol Export -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-calendar3 me-2"></i>Jadwal Perkuliahan
            </h4>
            <p class="text-secondary small mb-0">Daftar jadwal seluruh mata kuliah semester aktif.</p>
        </div>
        <div>
            <div class="dropdown">
                <button type="button" id="btnDownloadSchedule" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                    <i class="bi bi-download me-1"></i> Unduh Jadwal
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" id="btnExportPdf" class="dropdown-item" disabled><i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i>PDF</button></li>
                    <li><button type="button" id="btnExportPng" class="dropdown-item" disabled><i class="bi bi-file-earmark-image-fill me-2 text-primary"></i>PNG</button></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Alert Message Area -->
    <div id="scheduleAlert"></div>

    <!-- Printable & PDF Area -->
    <div id="printableScheduleArea">
        <!-- Banner Semester Aktif -->
        <div id="activeSemesterBanner" class="card border-0 shadow-sm rounded-4 mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6) !important;">
            <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-2 fw-medium">Semester Aktif</span>
                    <h5 class="fw-bold mb-1" id="activeSemesterTitle">Memuat Semester...</h5>
                    <small class="text-white-50" id="activeSemesterInfo">Program Studi & Angkatan</small>
                </div>
                <div class="text-end d-none d-md-block">
                    <i class="bi bi-calendar-week display-4 text-white opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Container Jadwal Per Hari -->
        <div id="dailyScheduleList" class="row g-4">
            <div class="col-12 text-center text-muted py-5">
                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                Memuat jadwal perkuliahan...
            </div>
        </div>
    </div>
</div>

<!-- Export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    const BASE_URL = "<?= BASE_URL ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/schedule.js"></script>
<style>
    .schedule-course-item {
        cursor: pointer;
        transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }
    .schedule-course-item:hover,
    .schedule-course-item:focus-visible {
        background-color: #f1f7ff !important;
        border-color: #86b7fe !important;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.1);
        transform: translateY(-1px);
    }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
