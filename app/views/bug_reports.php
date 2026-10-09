<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$userRole = $_SESSION['role_level'] ?? 'Keroco';
$isReviewer = in_array($userRole, ['Sepuh', 'Primordial'], true);
$pageTitle = 'Bug & Report';
$activeMenu = 'bug-report';
ob_start();
?>
<div class="container-fluid p-0">
    <div class="mb-4"><h4 class="fw-bold mb-1">Bug & Report</h4><p class="text-secondary small mb-0">Laporkan kendala agar dapat ditinjau tim pengelola.</p></div>
    <div id="bugReportAlert" aria-live="polite"></div>
    <?php if ($isReviewer): ?>
    <ul class="nav nav-tabs mb-3" id="bugReportTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="report-form-tab" data-bs-toggle="tab" data-bs-target="#report-form-pane" type="button" role="tab">Kirim Laporan</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="report-list-tab" data-bs-toggle="tab" data-bs-target="#report-list-pane" type="button" role="tab">Kumpulan Report</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="report-form-pane" role="tabpanel">
    <?php endif; ?>
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <form id="bugReportForm">
                    <label for="bugDescription" class="form-label fw-semibold">Deskripsi Bug atau Kendala</label>
                    <textarea class="form-control mb-3" id="bugDescription" name="description" rows="6" maxlength="10000" style="resize:none" required placeholder="Jelaskan langkah dan hasil yang terjadi."></textarea>
                    <button class="btn btn-primary" id="submitBugReport" type="submit">Kirim Laporan</button>
                </form>
            </div></div>
    <?php if ($isReviewer): ?>
        </div>
        <div class="tab-pane fade" id="report-list-pane" role="tabpanel">
            <div id="bugReportsList" class="d-flex flex-column gap-3"><div class="text-muted py-4">Memuat laporan...</div></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>const BASE_URL = "<?= BASE_URL ?>"; const CAN_REVIEW_BUG_REPORTS = <?= $isReviewer ? 'true' : 'false' ?>;</script>
<script src="<?= BASE_URL ?>public/js/modules/bug_reports.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
