<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

$userRole = $_SESSION['role_level'] ?? 'Keroco';
if (!in_array($userRole, ['Primordial', 'Sepuh'], true)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus untuk Primordial dan Sepuh.');
}

$pageTitle = 'Blacklist Email';
$activeMenu = 'blacklist';
ob_start();
?>
<div class="container-fluid p-0" id="blacklist-page">

    <!-- Header Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b, #334155);">
        <div class="card-body p-3 p-md-4 text-white d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-4 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 52px; height: 52px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px);">
                    <i class="bi bi-shield-x fs-3"></i>
                </div>
                <div>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-1 fw-medium" style="font-size: 0.75rem;">Keamanan & Moderasi</span>
                    <h5 class="fw-bold mb-0">Blacklist Email</h5>
                    <small class="text-white-50">Kelola daftar email terlarang, proteksi hierarki pemblokiran, dan sinkronisasi status akun.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Overview Chips -->
    <div class="row g-3 mb-4" id="blacklistStatsContainer">
        <div class="col-6 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Total Blacklist</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statTotalBlacklist">0</h4>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-slash-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Hard Block</div>
                        <h4 class="fw-bold text-danger mb-0 mt-1" id="statHardBlock">0</h4>
                    </div>
                    <div class="rounded-circle bg-dark bg-opacity-10 text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-shield-lock-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="blacklistAlert"></div>

    <!-- Action Button -->
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
            <i class="bi bi-plus-circle me-1"></i>Tambah Blacklist
        </button>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 ps-3 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchBlacklist" class="form-control bg-light border-0 py-2" placeholder="Cari alamat email, alasan, atau nama...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select id="filterBlockedByRole" class="form-select bg-light border-0 py-2">
                        <option value="all">Semua Pemblokir</option>
                        <option value="Primordial">Primordial</option>
                        <option value="Sepuh">Sepuh</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select id="filterStatus" class="form-select bg-light border-0 py-2">
                        <option value="all">Semua Status</option>
                        <option value="registered">Sudah Terdaftar (Users)</option>
                        <option value="unregistered">Belum Terdaftar</option>
                        <option value="hard_block">Hard Block</option>
                        <option value="regular">Blokir Biasa</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <select id="filterSort" class="form-select bg-light border-0 py-2">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="email_asc">Email (A-Z)</option>
                        <option value="email_desc">Email (Z-A)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Blacklist Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="fs-7 text-secondary fw-semibold">
                        <th class="ps-4 py-3" style="min-width: 200px;">Alamat Email</th>
                        <th class="py-3" style="min-width: 140px;">Status Terdaftar</th>
                        <th class="py-3" style="min-width: 120px;">Tipe Blokir</th>
                        <th class="py-3" style="min-width: 220px;">Alasan Pemblokiran</th>
                        <th class="py-3" style="min-width: 160px;">Ditambahkan Oleh</th>
                        <th class="py-3" style="min-width: 130px;">Tanggal Waktu</th>
                        <th class="pe-4 py-3 text-end" style="min-width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="blacklistTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                            Memuat daftar blacklist email...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="card-footer bg-white border-top border-light py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" id="blacklistPaginationContainer"></div>
    </div>
</div>

<!-- Modal Tambah Blacklist -->
<div class="modal fade" id="addBlacklistModal" tabindex="-1" aria-labelledby="addBlacklistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form id="formAddBlacklist">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="bi bi-shield-slash fs-5"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark" id="addBlacklistModalLabel">Tambah Blacklist Email</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" for="blacklistEmail">
                            Alamat Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control rounded-3" id="blacklistEmail" name="emailAddress" placeholder="nama@domain.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" for="blacklistReason">
                            Alasan Pemblokiran 
                            <?php if ($userRole === 'Sepuh'): ?>
                                <span class="text-danger">* (Wajib diisi oleh Sepuh)</span>
                            <?php endif; ?>
                        </label>
                        <textarea class="form-control rounded-3" id="blacklistReason" name="reasonDescription" rows="3" placeholder="Masukkan alasan pemblokiran email ini..." style="resize: none;"></textarea>
                    </div>

                    <?php if ($userRole === 'Primordial'): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="blCheckHardBlock" name="isHardBlock" value="1">
                        <label class="form-check-label text-dark fw-semibold" for="blCheckHardBlock">
                            Hard Block
                        </label>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="isHardBlock" value="0">
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-dark rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4 fw-semibold" id="btnSubmitBlacklist">
                        <i class="bi bi-shield-plus me-1"></i>Blacklist Akun
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Blacklist / Unblock -->
<div class="modal fade" id="confirmDeleteBlacklistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Hapus dari Blacklist</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body py-4">
                <p class="text-dark mb-2">
                    Apakah kamu yakin ingin menghapus <strong id="deleteBlacklistEmailText" class="text-primary">-</strong> dari daftar blacklist dan mengaktifkan akun ini?
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-semibold" id="btnConfirmDeleteBlacklist">
                    <i class="bi bi-unlock-fill me-1"></i>Unblock
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_USER_ROLE = "<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/blacklist.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
