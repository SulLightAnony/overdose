<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

$userRole = $_SESSION['role_level'] ?? 'Keroco';
if (!in_array($userRole, ['Primordial', 'Sepuh'], true)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus untuk Primordial dan Sepuh.');
}

$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($targetUserId <= 0) {
    header('Location: ' . BASE_URL . 'user-management');
    exit;
}

$pageTitle = 'Detail Pengguna';
$activeMenu = 'user-management';
ob_start();
?>
<div class="container-fluid p-0" id="user-detail-page" data-target-user-id="<?= $targetUserId ?>">

    <!-- Navigation Header & Breadcrumb -->
    <div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <a href="<?= BASE_URL ?>user-management" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div id="userDetailAlert"></div>

    <!-- User Profile & Moderation Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="profileCard">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-4">
                
                <!-- Profile Avatar & Details -->
                <div class="d-flex align-items-center gap-4 flex-grow-1" style="min-width: 0;">
                    <div class="d-flex flex-column align-items-center flex-shrink-0" style="min-width: 90px;">
                        <img id="detailAvatar" src="<?= BASE_URL ?>public/assets/img/logo.png" alt="Avatar" class="rounded-circle border shadow-sm" style="width: 80px; height: 80px; object-fit: cover;" referrerpolicy="no-referrer">
                        <div id="detailRoleBadge" class="mt-2 text-center w-100"></div>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h4 class="fw-bold text-dark mb-0" id="detailName" style="word-break: break-word;">Memuat Pengguna...</h4>
                            <span id="detailStatusBadge"></span>
                        </div>
                        <div class="text-secondary small mb-1" id="detailEmail" style="word-break: break-all;">-</div>
                        <div class="d-flex align-items-center gap-3 text-muted small flex-wrap" style="font-size: 0.8rem;">
                            <span id="detailAcademic"><i class="bi bi-mortarboard me-1"></i>-</span>
                            <span id="detailJoined"><i class="bi bi-calendar-event me-1"></i>-</span>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Controls -->
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end flex-shrink-0" id="userActionControls">
                    <!-- Populated dynamically via JS -->
                </div>

            </div>
        </div>

        <!-- Moderation Info Banner (Shown if blocked) -->
        <div id="moderationBannerContainer" class="d-none"></div>
    </div>

    <!-- Stat Counters Chips -->
    <div class="row g-3 mb-4" id="activityStatsRow">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Total Aktivitas</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statTotalActivity">0</h4>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-activity fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Tugas & Materi</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statTasksMaterials">0</h4>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-journal-text fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Jawaban & Diskusi</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statAnswersComments">0</h4>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-chat-quote fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Berita & Obrolan</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statNewsChats">0</h4>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-newspaper fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Activity History Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom border-light py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i> Riwayat Aktivitas & Kontribusi
                </h6>
                <small class="text-muted">Aktivitas ditampilkan secara transparan dan mengabaikan mode anonim pengguna.</small>
            </div>
        </div>

        <!-- Filter & Search Toolbar (Tombol Reset telah dihapus) -->
        <div class="card-body p-3 p-md-4 border-bottom border-light">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-pill text-muted ps-3">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" class="form-control bg-light border-start-0 rounded-end-pill" id="searchActivity" placeholder="Cari judul atau cuplikan konten...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select rounded-pill text-dark" id="filterActivityType">
                        <option value="all">Semua Jenis Upload</option>
                        <option value="task">Tugas</option>
                        <option value="material">Materi</option>
                        <option value="answer">Jawaban Tugas</option>
                        <option value="comment">Komentar Tugas</option>
                        <option value="news">Berita Kelas</option>
                        <option value="global_news">Berita Global</option>
                        <option value="chat">Obrolan Global</option>
                        <option value="bug_report">Bug Report</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select rounded-pill text-dark" id="sortActivity">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Activity Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="activityTable">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-4 py-3" style="min-width: 320px;">Judul / Cuplikan Konten</th>
                        <th style="min-width: 160px;">Jenis Upload</th>
                        <th style="min-width: 180px;">Tanggal Waktu</th>
                        <th class="text-end pe-4" style="min-width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="activityTableBody">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                            Memuat riwayat aktivitas pengguna...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white border-top border-light py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" id="activityPaginationContainer"></div>
    </div>

</div>

<!-- Modal Blokir Pengguna -->
<div class="modal fade" id="modalBlockUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3 px-4">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-x"></i> Blokir Pengguna
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="formBlockUser">
                <div class="modal-body p-4">
                    <input type="hidden" id="blockTargetUserId" name="targetUserId" value="<?= $targetUserId ?>">
                    
                    <?php if ($userRole === 'Primordial'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Tipe Pemblokiran</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                <input class="form-check-input ms-1 me-2" type="radio" name="lockType" id="blockTypeNormal" value="none" checked>
                                <label class="form-check-label small" for="blockTypeNormal">
                                    <strong>Blokir Biasa</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pengguna diblokir, namun moderator Sepuh tetap dapat membuka blokir.</div>
                                </label>
                            </div>
                            <div class="form-check p-2.5 border border-danger border-opacity-25 rounded-3 bg-danger bg-opacity-10 d-flex align-items-center">
                                <input class="form-check-input ms-1 me-2" type="radio" name="lockType" id="blockTypeHard" value="hard_block">
                                <label class="form-check-label small text-dark" for="blockTypeHard">
                                    <strong class="text-danger">Hard Block (Kunci Primordial)</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pengguna diblokir permanen oleh Primordial. Sepuh tidak dapat membuka blokir akun ini.</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="lockType" value="none">
                    <?php endif; ?>

                    <div class="mb-2">
                        <label for="blockReasonDescription" class="form-label fw-semibold small text-dark d-flex justify-content-between align-items-center">
                            <span>Alasan Pemblokiran</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">
                                <?= ($userRole === 'Sepuh') ? '<span class="text-danger fw-semibold">Wajib diisi</span>' : 'Opsional' ?>
                            </span>
                        </label>
                        <textarea class="form-control rounded-3" id="blockReasonDescription" name="reasonDescription" rows="3" placeholder="Tuliskan alasan pemblokiran pengguna ini..." <?= ($userRole === 'Sepuh') ? 'required' : '' ?> style="resize: none;"></textarea>
                        <div class="form-text small text-muted" style="font-size: 0.72rem;">
                            Email pengguna akan disinkronkan ke daftar blacklist secara otomatis.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top border-light">
                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-pill btn-sm px-4 fw-semibold" id="btnSubmitBlock">
                        Konfirmasi Blokir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Buka Blokir Pengguna -->
<div class="modal fade" id="modalUnblockUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white border-0 py-3 px-4">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle"></i> Buka Blokir Pengguna
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="formUnblockUser">
                <div class="modal-body p-4">
                    <input type="hidden" id="unblockTargetUserId" name="targetUserId" value="<?= $targetUserId ?>">
                    <p class="small text-secondary mb-3">
                        Membuka blokir akan mengaktifkan kembali akun pengguna dan menghapus email dari daftar blacklist email.
                    </p>

                    <?php if ($userRole === 'Primordial'): ?>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-dark">Pilihan Status Setelah Dibuka</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-2.5 border rounded-3 bg-light d-flex align-items-center">
                                <input class="form-check-input ms-1 me-2" type="radio" name="newLockType" id="unblockTypeNormal" value="none" checked>
                                <label class="form-check-label small" for="unblockTypeNormal">
                                    <strong>Unblock Biasa</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pengguna aktif kembali secara normal.</div>
                                </label>
                            </div>
                            <div class="form-check p-2.5 border border-success border-opacity-25 rounded-3 bg-success bg-opacity-10 d-flex align-items-center">
                                <input class="form-check-input ms-1 me-2" type="radio" name="newLockType" id="unblockTypeHard" value="hard_unblock">
                                <label class="form-check-label small text-dark" for="unblockTypeHard">
                                    <strong class="text-success">Hard Unblock (Proteksi Primordial)</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pengguna dilindungi dari pemblokiran oleh Sepuh di masa mendatang.</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="newLockType" value="none">
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top border-light">
                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill btn-sm px-4 fw-semibold" id="btnSubmitUnblock">
                        Buka Blokir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ubah Role (Primordial Only) -->
<?php if ($userRole === 'Primordial'): ?>
<div class="modal fade" id="modalChangeRole" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3 px-4">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-person-gear"></i> Ubah Peran
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="formChangeRole">
                <div class="modal-body p-4">
                    <input type="hidden" id="roleTargetUserId" name="targetUserId" value="<?= $targetUserId ?>">
                    <div class="mb-2">
                        <label for="selectNewRole" class="form-label fw-semibold small text-dark">Pilih Peran Baru</label>
                        <select class="form-select rounded-3" id="selectNewRole" name="newRole" required>
                            <option value="Keroco">Keroco</option>
                            <option value="Sepuh">Sepuh</option>
                        </select>
                        <div class="form-text small text-muted" style="font-size: 0.72rem;">Peran Primordial tidak dapat diberikan kepada akun lain.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top border-light">
                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark rounded-pill btn-sm px-3 fw-semibold" id="btnSubmitRole">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CURRENT_USER_ROLE = "<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>";
const CURRENT_USER_ID = <?= (int)$_SESSION['user_id'] ?>;
const TARGET_USER_ID = <?= $targetUserId ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/user_detail.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
