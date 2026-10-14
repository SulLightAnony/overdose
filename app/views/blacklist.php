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
            <div class="d-flex align-items-center gap-2">
                
                <button type="button" class="btn btn-light rounded-pill btn-sm px-3 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Blacklist
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Overview Chips -->
    <div class="row g-3 mb-4" id="blacklistStatsContainer">
        <div class="col-6 col-md-3">
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
        <div class="col-6 col-md-3">
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
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Kunci Primordial</div>
                        <h4 class="fw-bold text-primary mb-0 mt-1" id="statPrimordial">0</h4>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-shield-shaded fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Moderasi Sepuh</div>
                        <h4 class="fw-bold text-secondary mb-0 mt-1" id="statSepuh">0</h4>
                    </div>
                    <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="blacklistAlert"></div>

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
                <div class="col-6 col-md-2">
                    <select id="filterSort" class="form-select bg-light border-0 py-2">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="email_asc">Email (A-Z)</option>
                        <option value="email_desc">Email (Z-A)</option>
                    </select>
                </div>
                <div class="col-6 col-md-1 d-flex justify-content-end">
                    <button type="button" id="btnResetBlacklistFilter" class="btn btn-outline-secondary rounded-pill btn-sm w-100 py-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Blacklist Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-uppercase fs-7 text-secondary fw-semibold">
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
        <div class="card-footer bg-white border-0 py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2" id="blacklistPaginationContainer">
            <small class="text-muted" id="blacklistPaginationInfo">Menampilkan 0 data</small>
            <nav aria-label="Blacklist pagination">
                <ul class="pagination pagination-sm mb-0 gap-1" id="blacklistPaginationList"></ul>
            </nav>
        </div>
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
                        <small class="text-muted d-block mt-1">
                            Jika email terdaftar pada pengguna aktif di sistem, status akun akan otomatis disinkronkan menjadi Diblokir.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" for="blacklistReason">
                            Alasan Pemblokiran 
                            <?php if ($userRole === 'Sepuh'): ?>
                                <span class="text-danger">* (Wajib diisi oleh Sepuh)</span>
                            <?php else: ?>
                                <span class="text-muted fw-normal">(Opsional untuk Primordial)</span>
                            <?php endif; ?>
                        </label>
                        <textarea class="form-control rounded-3" id="blacklistReason" name="reasonDescription" rows="3" placeholder="Masukkan alasan pemblokiran email ini..."></textarea>
                    </div>

                    <?php if ($userRole === 'Primordial'): ?>
                    <div class="p-3 bg-light rounded-4 border mb-3">
                        <label class="form-label fw-semibold text-dark mb-2">Tingkat Penguncian Blokir:</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="isHardBlock" id="blChoiceNormal" value="0" checked>
                            <label class="form-check-label text-dark" for="blChoiceNormal">
                                <strong>Blokir Biasa</strong>
                                <span class="d-block text-muted small">Moderator Sepuh masih memiliki hak untuk membuka blokir email ini.</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="isHardBlock" id="blChoiceHard" value="1">
                            <label class="form-check-label text-dark" for="blChoiceHard">
                                <strong class="text-danger"><i class="bi bi-shield-lock-fill me-1"></i>Hard Block (Keras)</strong>
                                <span class="d-block text-muted small">Email diblokir keras secara permanen oleh Primordial. Sepuh tidak akan bisa membuka atau menghapus blokir ini.</span>
                            </label>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="isHardBlock" value="0">
                    <?php endif; ?>

                    <div class="alert alert-secondary border-0 rounded-3 mb-0 small text-secondary">
                        <i class="bi bi-info-circle me-1"></i>
                        Akun dengan role <strong>Primordial</strong> kebal dari pemblokiran. Sepuh tidak dapat memblacklist sesama Sepuh.
                    </div>
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
                    Apakah kamu yakin ingin menghapus <strong id="deleteBlacklistEmailText" class="text-primary">-</strong> dari daftar blacklist dan kembali mengaktifkan akun ini?
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-semibold" id="btnConfirmDeleteBlacklist">
                    <i class="bi bi-unlock-fill me-1"></i>Hapus & Aktifkan
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
