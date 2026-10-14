<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
require_once __DIR__ . '/../api/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role_level'] ?? 'Keroco';
$isReviewer = in_array($userRole, ['Sepuh', 'Primordial'], true);
$pageTitle = 'Bug & Report';
$activeMenu = 'bug-report';

// Cek sisa cooldown laporan untuk user saat ini:
// Primordial: Tanpa batas waktu (0 detik)
// Sepuh: 1 Menit (60 detik)
// Keroco / Lainnya: 1 Jam (3600 detik)
$cooldownLimit = match ($userRole) {
    'Primordial' => 0,
    'Sepuh' => 60,
    default => 3600
};

$cooldownSeconds = 0;
if ($userId > 0 && $cooldownLimit > 0) {
    try {
        $cooldownStmt = $pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed_seconds
            FROM bug_reports
            WHERE user_id = :userId
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $cooldownStmt->execute(['userId' => $userId]);
        $elapsed = $cooldownStmt->fetchColumn();
        if ($elapsed !== false && (int)$elapsed < $cooldownLimit) {
            $cooldownSeconds = $cooldownLimit - (int)$elapsed;
        }
    } catch (Throwable $e) {
        $cooldownSeconds = 0;
    }
}

ob_start();
?>
<style>
/* Lingkaran notifikasi tab Kumpulan Report:
   Biru jika tab sedang tidak dipilih, putih jika tab sedang dipilih.
   Warna teks sebaliknya. */
#report-list-tab #badgeReportCount {
    background-color: #0d6efd !important;
    color: #ffffff !important;
    font-size: 0.72rem;
    padding: 0.25em 0.6em;
    transition: all 0.2s ease-in-out;
}
#report-list-tab.active #badgeReportCount {
    background-color: #ffffff !important;
    color: #0d6efd !important;
}
</style>

<div class="container-fluid p-0" id="bug-reports-page">

    <!-- Header Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b, #334155);">
        <div class="card-body p-3 p-md-4 text-white d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-4 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 52px; height: 52px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px);">
                    <i class="bi bi-bug-fill fs-3"></i>
                </div>
                <div>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-1 fw-medium" style="font-size: 0.75rem;">Pusat Bantuan & Masukan</span>
                    <h5 class="fw-bold mb-0">Bug & Report</h5>
                    <small class="text-white-50">Laporkan kendala teknis atau kirimkan saran fitur untuk pengembangan aplikasi.</small>
                </div>
            </div>
        </div>
    </div>

    <div id="bugReportAlert" aria-live="polite"></div>

    <?php if ($isReviewer): ?>
    <!-- Navigation Tabs for Reviewers -->
    <ul class="nav nav-pills gap-2 mb-4 p-1 bg-light rounded-pill border d-inline-flex" id="bugReportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill active fw-semibold px-4 py-2 small" id="report-form-tab" data-bs-toggle="tab" data-bs-target="#report-form-pane" type="button" role="tab">
                <i class="bi bi-pencil-square me-1"></i>Kirim Laporan
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill fw-semibold px-4 py-2 small" id="report-list-tab" data-bs-toggle="tab" data-bs-target="#report-list-pane" type="button" role="tab">
                <i class="bi bi-inbox-fill me-1"></i>Kumpulan Report
                <span class="badge rounded-pill ms-1" id="badgeReportCount" style="display: none;">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Form -->
        <div class="tab-pane fade show active" id="report-form-pane" role="tabpanel">
    <?php endif; ?>

            <?php if ($userRole !== 'Primordial'): ?>
            <div class="alert alert-info border-0 rounded-3 small text-dark d-flex align-items-start gap-2 mb-3 bg-primary bg-opacity-10">
                <i class="bi bi-info-circle-fill text-primary fs-5 mt-0.5 flex-shrink-0"></i>
                <div class="mt-1">
                    <?php if ($userRole === 'Sepuh'): ?>
                        <strong>Informasi:</strong> Kamu bisa mengirim laporan <strong>1 kali setiap 1 menit</strong>.
                    <?php else: ?>
                        <strong>Informasi:</strong> Kamu hanya bisa mengirim laporan <strong>1 kali setiap 1 jam</strong>.
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom border-light py-3 px-4">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-chat-left-dots text-primary"></i> Form Laporan Kendala & Saran
                    </h6>
                </div>
                <div class="card-body p-4">
                    <form id="bugReportForm">
                        <div class="mb-3">
                            <label for="bugDescription" class="form-label fw-semibold text-dark small">Deskripsi Bug atau Saran Fitur</label>
                            <textarea class="form-control rounded-3" id="bugDescription" name="description" rows="6" maxlength="10000" style="resize: none;" required placeholder="Beritahu bug atau beritahu saran fitur yang kamu harapkan secara detail..."></textarea>
                            <div class="form-text small text-muted">Jelaskan sedetail mungkin langkah kejadian atau ide fitur yang ingin diajukan.</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-outline-dark rounded-pill btn-sm px-4 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" id="submitBugReport" type="submit">
                                <i class="bi bi-send"></i> Kirim Laporan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

    <?php if ($isReviewer): ?>
        </div>

        <!-- Tab 2: Report List for Reviewers -->
        <div class="tab-pane fade" id="report-list-pane" role="tabpanel">
            
            <!-- Filter & Search Toolbar (Tombol Reset telah dihapus) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-pill text-muted ps-3">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" class="form-control bg-light border-start-0 rounded-end-pill" id="searchBugReport" placeholder="Cari isi laporan / nama...">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select class="form-select rounded-pill text-dark" id="filterStatusBugReport">
                                <option value="all">Semua Status</option>
                                <option value="in_progress">Diproses</option>
                                <option value="resolved">Selesai</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select class="form-select rounded-pill text-dark" id="sortBugReport">
                                <option value="newest">Terbaru</option>
                                <option value="oldest">Terlama</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Container -->
            <div id="bugReportsList" class="d-flex flex-column gap-3">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                    Memuat daftar laporan...
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2" id="bugReportPaginationContainer"></div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CAN_REVIEW_BUG_REPORTS = <?= $isReviewer ? 'true' : 'false' ?>;
const INITIAL_COOLDOWN_SECONDS = <?= (int)$cooldownSeconds ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/bug_reports.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
