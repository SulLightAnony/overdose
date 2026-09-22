<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Daftar Semester';
$activeMenu = 'semester';

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Header Page -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Daftar Semester</h4>
            <p class="text-secondary small mb-0">Pilih semester untuk melihat mata kuliah dan daftar tugas.</p>
        </div>
        <div id="manager-actions" class="d-none">
            <button type="button" class="btn btn-dark btn-sm rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalSemester" onclick="openAddModal()">
                <i class="bi bi-plus-lg me-1"></i> Tambah Semester
            </button>
        </div>
    </div>

    <!-- Grid Semester -->
    <div class="row g-3 g-md-4" id="semester-grid">
        <!-- Skeleton Loader -->
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background-color: #cbd5e1;">
                <span class="placeholder col-6 py-2 rounded mb-2"></span>
                <span class="placeholder col-10 py-3 rounded"></span>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background-color: #cbd5e1;">
                <span class="placeholder col-6 py-2 rounded mb-2"></span>
                <span class="placeholder col-10 py-3 rounded"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah / Edit Semester (Khusus Primordial & Sepuh) -->
<div class="modal fade" id="modalSemester" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalSemesterTitle">Tambah Semester</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSemester">
                <input type="hidden" id="semesterId" name="semesterId" value="">
                <input type="hidden" id="formMethod" name="_method" value="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">Nomor Semester</label>
                        <input type="number" class="form-control rounded-3" id="semesterNumber" name="semesterNumber" min="1" max="14" required placeholder="Contoh: 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">Judul Semester</label>
                        <input type="text" class="form-control rounded-3" id="semesterTitle" name="semesterTitle" required placeholder="Contoh: Semester 1 (Ganjil)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">Warna Card Background</label>
                        <input type="color" class="form-control form-control-color w-100 rounded-3" id="backgroundColor" name="backgroundColor" value="#3b82f6">
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="btnSaveSemester">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Semester -->
<div class="modal fade" id="modalDeleteConfirm" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Peringatan Kritis!</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-dark mb-2">Tindakan ini <strong>TIDAK DAPAT DIBATALKAN</strong>.</p>
                <p class="text-muted small mb-4">Menghapus semester ini akan secara permanen menghapus <strong>semua mata kuliah</strong> dan <strong>seluruh tugas</strong> yang ada di dalamnya.</p>
                
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-danger">Ketik kalimat berikut untuk konfirmasi:</label>
                    <div class="p-2 bg-light border rounded small mb-2 user-select-none" style="font-style: italic;">
                        Saya <?= htmlspecialchars($_SESSION['user_name']) ?> mengerti bahwa dengan menghapus semester maka segala mata kuliah serta tugas di dalam semester ini akan ikut terhapus.
                    </div>
                    <input type="text" class="form-control form-control-sm" id="confirmDeleteText" placeholder="Ketik persis seperti kalimat di atas" autocomplete="off">
                </div>
                
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm btn-danger px-4 fw-semibold" id="btnConfirmDelete" disabled>Hapus Semester</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const USER_NAME = "<?= addslashes($_SESSION['user_name']) ?>";
</script>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/semester.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>