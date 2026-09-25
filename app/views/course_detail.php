<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Course Detail (Course Main View - Tasks & Materials)
 * FILE LOCATION: app/views/course_detail.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) utama untuk Halaman Detail Mata Kuliah.
 * Berfungsi sebagai penampung informasi lengkap matkul, bilah pencarian & pengurutan,
 * serta 2 tab horizontal utama: Tab "Daftar Tugas" dan Tab "Materi Pembelajaran",
 * lengkap dengan modal form penambahan data (dukungan multi-file upload & auto-title).
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Layout Wrapper: app/views/layout.php
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - Backend API Endpoint: app/api/courses.php, app/api/tasks.php, app/api/materials.php
 * - Frontend Script Module: public/js/modules/course_detail.js
 * - Detail Views Navigation: app/views/task_detail.php, app/views/material_detail.php
 * 
 * STRUCTURE & KOMPONEN UI (UI COMPONENTS):
 * 1. Header Course Card (Top Section):
 *    - Menampilkan Kode Matkul, Judul Matkul, Tipe (Teori/Praktek), Dosen Pengampu, 
 *      Kontak Dosen (Email/HP), Jadwal Hari & Jam, serta Ruangan/Kelas.
 *    - Desain visual disesuaikan dengan tema kartu semester/course.
 * 
 * 2. Controls & Filter Bar (Search & Sort):
 *    - Search Input: Filtering teks secara real-time berdasarkan judul/deskripsi.
 *    - Dropdown Sort Dynamic (Menyesuaikan Tab Aktif):
 *      * Pilihan Sort Tab Tugas: Judul (ASC/DESC), Deadline (ASC/DESC).
 *      * Pilihan Sort Tab Materi: Judul (ASC/DESC), Tanggal Dibuat (ASC/DESC).
 *    - Action Buttons:
 *      * Tombol "Tambah Tugas" (Membuka Modal Form Tambah Tugas).
 *      * Tombol "Tambah Materi" (Membuka Modal Form Tambah Materi).
 *      * Dapat diakses oleh semua pengguna dari role Keroco hingga Primordial (+1 Poin Kontribusi).
 * 
 * 3. Tab Container Horizontal:
 *    - Tab 1: "Tugas Kuliah"
 *    - Tab 2: "Materi Pembelajaran"
 * 
 * 4. List Container Tab 1 (Daftar Tugas - Lazy Loading):
 *    - Kartu Tugas Aktif (deletionStatus = 0):
 *      * Menampilkan Judul, Deskripsi Singkat (truncated), Deadline, Badge Status (Tersedia / Selesai / Telat).
 *      * Dapat diklik untuk navigasi penuh menuju Halaman Detail Tugas (`task_detail.php?id=X`).
 *    - Kartu Tugas Dihapus (deletionStatus = 1 / Soft-Delete):
 *      * Ditaruh di urutan paling bawah daftar.
 *      * Tampilan visual disabled/faded out, TIDAK BISA DIKLIK sama sekali.
 *      * Menampilkan judul & deskripsi singkat, namun tanpa badge status/deadline.
 *      * Dilengkapi banner teks informasi wajib: "Dihapus oleh [userName] pada [timestamp]".
 * 
 * 5. List Container Tab 2 (Daftar Materi - Lazy Loading):
 *    - Desain kartu disamakan persis dengan kartu tugas (tanpa badge deadline/status selesai).
 *    - Kartu Materi Aktif (deletionStatus = 0):
 *      * Menampilkan Judul, Deskripsi Singkat, Tanggal Unggah, dan Author.
 *      * Dapat diklik untuk navigasi penuh ke Halaman Detail Materi (`material_detail.php?id=X`).
 *    - Kartu Materi Dihapus (deletionStatus = 1 / Soft-Delete):
 *      * Ditaruh di urutan paling bawah daftar.
 *      * Tampilan visual disabled/faded out, TIDAK BISA DIKLIK sama sekali.
 *      * Dilengkapi banner teks informasi wajib: "Dihapus oleh [userName] pada [timestamp]".
 * 
 * 6. Modal Form Tambah / Edit Tugas:
 *    - Input: Judul Tugas, Tipe (Teori/Praktek), Tanggal & Jam Deadline, Deskripsi.
 *    - Input File Lampiran: Multi-file upload support (`input type="file" multiple`).
 * 
 * 7. Modal Form Tambah / Edit Materi:
 *    - Input File Lampiran: Multi-file upload support (`input type="file" multiple`).
 *    - Input Judul Materi: Auto-fill otomatis dari nama file pertama yang dipilih (tanpa ekstensi),
 *      tetapi tetap bisa diubah/diedit secara manual oleh pengguna.
 *    - Input Deskripsi Materi.
 * 
 * CARA KERJA SCRIPT FRONTEND (`course_detail.js`):
 * - Membaca `courseId` dari atribut data container HTML.
 * - Melakukan fetch AJAX ke `app/api/courses.php?action=detail&courseId=X` untuk header.
 * - Melakukan fetch AJAX lazy loading (10 item per batch) ke `courses.php?action=list_tasks` 
 *   atau `courses.php?action=list_materials` sesuai tab aktif, parameter search, dan sort.
 * - Mengelola logika event listener untuk pemicu modal upload, auto-title materi, dan tab switching.
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = 'Detail Mata Kuliah';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="course-detail-page" data-course-id="<?= $courseId ?>">
    
    <!-- 1. Header Course Card (Akan di-render oleh JS via course_detail.js) -->
    <div id="course-header-container" class="mb-4">
        <!-- Skeleton Loader (sementara sebelum fetch AJAX selesai) -->
        <div class="card shadow-sm placeholder-glow">
            <div class="card-body p-4">
                <h3 class="placeholder col-6 rounded mb-3"></h3>
                <p class="placeholder col-4 rounded"></p>
                <p class="placeholder col-5 rounded"></p>
            </div>
        </div>
    </div>

    <!-- 2. Controls & Filter Bar -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-4 mb-2 mb-md-0">
            <input type="text" id="searchInput" class="form-control" placeholder="Cari judul atau deskripsi...">
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
            <!-- Dropdown Sort Dinamis (Options diatur oleh JS tergantung tab aktif) -->
            <select id="sortSelect" class="form-select">
                <option value="due_asc">Deadline Terdekat</option>
                <option value="due_desc">Deadline Terjauh</option>
                <option value="title_asc">Judul (A-Z)</option>
                <option value="title_desc">Judul (Z-A)</option>
            </select>
        </div>
        <div class="col-md-5 text-md-end">
            <!-- Tombol Aksi -->
            <button class="btn btn-primary me-2" id="btnAddTask" data-bs-toggle="modal" data-bs-target="#taskModal">
                Tambah Tugas
            </button>
            <button class="btn btn-success" id="btnAddMaterial" data-bs-toggle="modal" data-bs-target="#materialModal">
                Tambah Materi
            </button>
        </div>
    </div>

    <!-- 3. Tab Container Horizontal -->
    <ul class="nav nav-tabs mb-4" id="courseTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="tab-tasks-btn" data-bs-toggle="tab" data-bs-target="#tab-tasks" type="button" role="tab" aria-controls="tab-tasks" aria-selected="true">
                Tugas Kuliah
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="tab-materials-btn" data-bs-toggle="tab" data-bs-target="#tab-materials" type="button" role="tab" aria-controls="tab-materials" aria-selected="false">
                Materi Pembelajaran
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="courseTabsContent">
        
        <!-- 4. List Container Tab 1 (Daftar Tugas) -->
        <div class="tab-pane fade show active" id="tab-tasks" role="tabpanel" aria-labelledby="tab-tasks-btn">
            <div id="tasks-list-container" class="row">
                <!-- Konten Tugas akan dirender oleh JS di sini -->
            </div>
            <div class="text-center mt-3 mb-5">
                <button id="btn-load-more-tasks" class="btn btn-outline-secondary d-none">Muat Lebih Banyak Tugas...</button>
                <div id="tasks-loading-spinner" class="spinner-border text-primary d-none" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>

        <!-- 5. List Container Tab 2 (Daftar Materi) -->
        <div class="tab-pane fade" id="tab-materials" role="tabpanel" aria-labelledby="tab-materials-btn">
            <div id="materials-list-container" class="row">
                <!-- Konten Materi akan dirender oleh JS di sini -->
            </div>
            <div class="text-center mt-3 mb-5">
                <button id="btn-load-more-materials" class="btn btn-outline-secondary d-none">Muat Lebih Banyak Materi...</button>
                <div id="materials-loading-spinner" class="spinner-border text-primary d-none" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- 6. Modal Form Tambah / Edit Tugas -->
<!-- ============================================================ -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formTask" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalLabel">Tambah Tugas Kuliah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="courseId" value="<?= $courseId ?>">
                    <input type="hidden" name="semesterId" id="inputTaskSemesterId" value="">
                    <!-- Field ID untuk keperluan Edit jika diterapkan kelak -->
                    <input type="hidden" name="taskId" id="inputTaskId" value="">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="taskTitle" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="taskTitle" name="taskTitle" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="taskType" class="form-label">Tipe Tugas</label>
                            <select class="form-select" id="taskType" name="taskType">
                                <option value="Teori">Teori</option>
                                <option value="Praktek">Praktek</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="dueDate" class="form-label">Tenggat Waktu (Deadline) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="dueDate" name="dueDate" required>
                    </div>

                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Deskripsi / Instruksi Tugas</label>
                        <textarea class="form-control" id="taskDescription" name="taskDescription" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="taskAttachments" class="form-label">Lampirkan File pendukung (Bisa lebih dari 1)</label>
                        <input class="form-control" type="file" id="taskAttachments" name="attachments[]" multiple>
                        <div class="form-text">Mendukung multi-file upload. (PDF, Word, Excel, PPT, ZIP, dll)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitTask">Simpan Tugas (+1 Poin)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- 7. Modal Form Tambah / Edit Materi -->
<!-- ============================================================ -->
<div class="modal fade" id="materialModal" tabindex="-1" aria-labelledby="materialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formMaterial" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="materialModalLabel">Bagikan Materi Pembelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="courseId" value="<?= $courseId ?>">
                    <input type="hidden" name="semesterId" id="inputMaterialSemesterId" value="">
                    <!-- Field ID untuk keperluan Edit jika diterapkan kelak -->
                    <input type="hidden" name="materialId" id="inputMaterialId" value="">

                    <div class="mb-3">
                        <label for="materialAttachments" class="form-label">Lampirkan File Materi (Bisa lebih dari 1) <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="materialAttachments" name="attachments[]" multiple required>
                        <div class="form-text">Judul otomatis akan diambil dari nama file pertama yang dipilih.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="materialTitle" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="materialTitle" name="materialTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="materialDescription" class="form-label">Catatan / Deskripsi Materi</label>
                        <textarea class="form-control" id="materialDescription" name="materialDescription" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitMaterial">Bagikan Materi (+1 Poin)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_COURSE_ID = <?= $courseId ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/course_detail.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>