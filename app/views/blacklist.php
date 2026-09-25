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
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Blacklist Email</h4>
            <p class="text-secondary small mb-0">Kelola email yang tidak boleh mendaftar.</p>
        </div>
        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">Tambah Blacklist</button>
    </div>
    <div id="blacklistAlert"></div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Email</th><th>Alasan</th><th>Ditambahkan oleh</th><th class="text-end">Aksi</th></tr></thead>
                <tbody id="blacklistTableBody"><tr><td colspan="4" class="text-center text-muted py-4">Memuat blacklist...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addBlacklistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="formAddBlacklist">
            <div class="modal-header"><h5 class="modal-title">Tambah Email ke Blacklist</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_blacklist">
                <label class="form-label" for="blacklistEmail">Alamat Email</label>
                <input type="email" class="form-control mb-3" id="blacklistEmail" name="emailAddress" required>
                <label class="form-label" for="blacklistReason">Alasan</label>
                <textarea class="form-control" id="blacklistReason" name="reasonDescription" rows="3"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-dark" id="btnSubmitBlacklist">Simpan</button></div>
        </form>
    </div></div>
</div>
<script>const BASE_URL = "<?= BASE_URL ?>"; const CURRENT_USER_ROLE = "<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>";</script>
<script src="<?= BASE_URL ?>public/js/modules/blacklist.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
