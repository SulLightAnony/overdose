<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

if (($_SESSION['role_level'] ?? 'Keroco') !== 'Primordial') {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus untuk Primordial.');
}

$pageTitle = 'Manajemen Pengguna';
$activeMenu = 'user-management';
ob_start();
?>
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Manajemen Pengguna</h4>
            <p class="text-secondary small mb-0">Kelola role dan status akses pengguna.</p>
        </div>
        <input type="search" class="form-control form-control-sm" id="searchUser" placeholder="Cari nama/email..." style="max-width: 240px;">
    </div>
    <div id="userManagementAlert"></div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Pengguna</th><th>Role</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody id="usersTableBody"><tr><td colspan="4" class="text-center text-muted py-4">Memuat data pengguna...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
<script>const BASE_URL = "<?= BASE_URL ?>";</script>
<script src="<?= BASE_URL ?>public/js/modules/user_management.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
