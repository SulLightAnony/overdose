<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Mata Kuliah';
$activeMenu = 'semester';

$semesterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($semesterId <= 0) {
    header('Location: ' . BASE_URL . 'semester');
    exit;
}

ob_start();
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <a href="<?= BASE_URL ?>semester" class="btn btn-sm btn-light border rounded-pill px-3 mb-2 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <h4 class="fw-bold text-dark mb-1">Daftar Mata Kuliah 📖</h4>
            <p class="text-secondary small mb-0">Pilih mata kuliah untuk melihat tugas-tugas di dalamnya.</p>
        </div>
        <div id="manager-actions" class="d-none">
            <button type="button" class="btn btn-dark btn-sm rounded-pill px-3 fw-semibold" onclick="openAddModal()">
                <i class="bi bi-plus-lg me-1"></i> Tambah Matkul
            </button>
        </div>
    </div>

    <div class="row g-3 g-md-4" id="course-grid">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white card-gradient" style="--card-bg: #cbd5e1;">
                <span class="placeholder col-4 py-2 rounded mb-2"></span>
                <span class="placeholder col-8 py-3 rounded mb-3"></span>
                <span class="placeholder col-12 py-1 rounded mb-1"></span>
                <span class="placeholder col-10 py-1 rounded"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Course -->
<div class="modal fade" id="modalCourse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalCourseTitle">Tambah Mata Kuliah</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCourse">
                <input type="hidden" id="courseId" name="courseId" value="">
                <input type="hidden" id="semesterId" name="semesterId" value="<?= $semesterId ?>">
                <input type="hidden" id="formMethod" name="_method" value="POST">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Kode Matkul</label>
                            <input type="text" class="form-control rounded-3" id="courseCode" name="courseCode" required placeholder="Contoh: IF101">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Judul Mata Kuliah</label>
                            <input type="text" class="form-control rounded-3" id="courseTitle" name="courseTitle" required placeholder="Contoh: Algoritma & Pemrograman">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Tipe Matkul</label>
                            <select class="form-select rounded-3" id="courseType" name="courseType" required>
                                <option value="Teori">Teori</option>
                                <option value="Praktek">Praktek</option>
                            </select>
                        </div>
                        <hr class="my-3 text-secondary opacity-25">
                        <h6 class="fw-bold mb-2">Jadwal & Kelas</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">Kelas</label>
                                <input type="text" class="form-control rounded-3" id="courseClass" name="courseClass" placeholder="Contoh: 1A-D4">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">Hari</label>
                                <select class="form-select rounded-3" id="courseDay" name="courseDay">
                                    <option value="">- Pilih Hari -</option>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                    <option value="Minggu">Minggu</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">Jam Mulai</label>
                                <input type="time" class="form-control rounded-3" id="startTime" name="startTime">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">Jam Selesai</label>
                                <input type="time" class="form-control rounded-3" id="endTime" name="endTime">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Deskripsi (Opsional)</label>
                            <textarea class="form-control rounded-3" id="courseDescription" name="courseDescription" rows="2" placeholder="Penjelasan singkat mata kuliah..."></textarea>
                        </div>
                        <hr class="my-2 text-secondary opacity-25">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Nama Dosen</label>
                            <input type="text" class="form-control rounded-3" id="lecturerName" name="lecturerName" placeholder="Contoh: Budi Santoso, S.T., M.Kom.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Email Dosen</label>
                            <input type="email" class="form-control rounded-3" id="lecturerEmail" name="lecturerEmail" placeholder="dosen@kampus.ac.id">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">No. WhatsApp</label>
                            <input type="text" class="form-control rounded-3" id="lecturerPhone" name="lecturerPhone" placeholder="Contoh: 6281234567890">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Warna Card Background</label>
                            <input type="color" class="form-control form-control-color w-100 rounded-3" id="backgroundColor" name="backgroundColor" value="#10b981">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="btnSaveCourse">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalDeleteConfirm" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Peringatan Penghapusan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Tindakan ini akan menghapus mata kuliah beserta <strong>seluruh tugas</strong> di dalamnya.</p>
                
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-danger">Ketik kalimat berikut untuk konfirmasi:</label>
                    <div class="p-2 bg-light border rounded small mb-2 user-select-none fw-medium" style="font-style: italic;">
                        Saya <?= htmlspecialchars($_SESSION['user_name']) ?> mengerti bahwa dengan menghapus mata kuliah ini maka seluruh tugas di dalamnya akan ikut terhapus.
                    </div>
                    <input type="text" class="form-control form-control-sm" id="confirmDeleteText" placeholder="Ketik konfirmasi..." autocomplete="off">
                </div>
                
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm btn-danger px-4 fw-semibold" id="btnConfirmDelete" disabled>Hapus Matkul</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_SEMESTER_ID = <?= $semesterId ?>;
    const USER_NAME = "<?= addslashes($_SESSION['user_name']) ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/course.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>