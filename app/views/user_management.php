<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

$userRole = $_SESSION['role_level'] ?? 'Keroco';
if (!in_array($userRole, ['Primordial', 'Sepuh'], true)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus untuk Primordial dan Sepuh.');
}

$pageTitle = 'Manajemen Pengguna';
$activeMenu = 'user-management';
ob_start();
?>
<div class="container-fluid p-0" id="user-management-page">

    <!-- Header Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b, #334155);">
        <div class="card-body p-3 p-md-4 text-white d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-4 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 52px; height: 52px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px);">
                    <i class="bi bi-people-fill fs-3"></i>
                </div>
                <div>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-1 fw-medium" style="font-size: 0.75rem;">Administrasi Sistem</span>
                    <h5 class="fw-bold mb-0">Manajemen Pengguna</h5>
                    <small class="text-white-50">Kelola hak akses pengguna, status akun, dan moderasi komunitas secara terpusat.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Overview Chips -->
    <div class="row g-3 mb-4" id="userStatsContainer">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Total Pengguna</div>
                        <h4 class="fw-bold text-dark mb-0 mt-1" id="statTotalUsers">0</h4>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Akun Aktif</div>
                        <h4 class="fw-bold text-success mb-0 mt-1" id="statActiveUsers">0</h4>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Akun Diblokir</div>
                        <h4 class="fw-bold text-danger mb-0 mt-1" id="statBlockedUsers">0</h4>
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
                        <div class="text-muted small fw-medium">Role = Sepuh</div>
                        <h4 class="fw-bold text-secondary mb-0 mt-1" id="statSepuhUsers">0</h4>
                    </div>
                    <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="bi bi-shield-shaded fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="userManagementAlert"></div>

    <!-- Filter & Search Toolbar (Tombol Reset telah dihapus) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-pill text-muted ps-3">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" class="form-control bg-light border-start-0 rounded-end-pill" id="searchUser" placeholder="Cari nama atau email...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select rounded-pill text-dark" id="filterRole">
                        <option value="all">Semua Role</option>
                        <option value="Primordial">Primordial</option>
                        <option value="Sepuh">Sepuh</option>
                        <option value="Keroco">Keroco</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select rounded-pill text-dark" id="filterStatus">
                        <option value="all">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="blocked">Diblokir</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select rounded-pill text-dark" id="sortUsers">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="name_asc">Nama (A-Z)</option>
                        <option value="name_desc">Nama (Z-A)</option>
                        <option value="role">Urutan Role</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-4 py-3" style="min-width: 280px;">Pengguna</th>
                        <th style="min-width: 160px;">Status & Moderasi</th>
                        <th style="min-width: 130px;">Bergabung</th>
                        <th class="text-end pe-4" style="min-width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                            Memuat data pengguna...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top border-light py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" id="userPaginationContainer"></div>
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
                    <input type="hidden" id="blockTargetUserId" name="targetUserId">
                    
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-3">
                        <img id="blockUserAvatar" src="" class="rounded-circle border" style="width: 44px; height: 44px; object-fit: cover;" alt="Avatar" referrerpolicy="no-referrer">
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-dark text-truncate" id="blockUserName">-</div>
                            <div class="text-muted small text-truncate" id="blockUserEmail">-</div>
                        </div>
                    </div>

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
                    <input type="hidden" id="unblockTargetUserId" name="targetUserId">
                    
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-3">
                        <img id="unblockUserAvatar" src="" class="rounded-circle border" style="width: 44px; height: 44px; object-fit: cover;" alt="Avatar" referrerpolicy="no-referrer">
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-dark text-truncate" id="unblockUserName">-</div>
                            <div class="text-muted small text-truncate" id="unblockUserEmail">-</div>
                        </div>
                    </div>

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
                    <input type="hidden" id="roleTargetUserId" name="targetUserId">
                    <div class="text-center mb-3">
                        <img id="roleUserAvatar" src="" class="rounded-circle border mb-2" style="width: 52px; height: 52px; object-fit: cover;" alt="Avatar" referrerpolicy="no-referrer">
                        <div class="fw-bold text-dark" id="roleUserName">-</div>
                        <div class="text-muted small" id="roleUserCurrent">-</div>
                    </div>
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
</script>
<script src="<?= BASE_URL ?>public/js/modules/user_management.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
