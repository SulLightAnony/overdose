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
        <div class="card border-0 shadow-sm rounded-4 p-4 text-white placeholder-glow card-gradient" style="--card-bg: #cbd5e1;">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div class="flex-grow-1">
                    <span class="placeholder col-2 py-2 rounded-pill mb-2"></span>
                    <span class="placeholder col-6 py-3 rounded mb-3"></span>
                    <span class="placeholder col-3 py-2 rounded mb-2"></span>
                    <span class="placeholder col-10 py-1 rounded"></span>
                </div>
                <div class="text-end d-none d-md-block ms-auto">
                    <i class="bi bi-book-half display-4 text-white opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Navigasi Tab & Kontrol Tombol Tambah (Dynamic Visibility) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <!-- Nav Tabs (Gaya Obrolan Global | Berita Global) -->
        <ul class="nav nav-pills gap-2 bg-white p-2 rounded-4 shadow-sm border border-light d-inline-flex mb-0" id="courseTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 fw-semibold small" id="tab-tasks-btn" data-bs-toggle="pill" data-bs-target="#tab-tasks" type="button" role="tab" aria-controls="tab-tasks" aria-selected="true">
                    <i class="bi bi-card-checklist me-2"></i>Tugas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 fw-semibold small" id="tab-materials-btn" data-bs-toggle="pill" data-bs-target="#tab-materials" type="button" role="tab" aria-controls="tab-materials" aria-selected="false">
                    <i class="bi bi-journal-bookmark-fill me-2"></i>Materi
                </button>
            </li>
        </ul>

        <!-- Action Buttons (Kontekstual per Tab Aktif) -->
        <div>
            <button class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" id="btnAddTask" data-bs-toggle="modal" data-bs-target="#taskModal">
                + Tambah Tugas
            </button>
            <button class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-none" id="btnAddMaterial" data-bs-toggle="modal" data-bs-target="#materialModal">
                + Tambah Materi
            </button>
        </div>
    </div>

    <!-- Controls & Filter Bar (Search & Sort) -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6 col-lg-5 mb-2 mb-md-0">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted rounded-start-pill ps-3">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-pill" placeholder="Cari judul atau deskripsi...">
            </div>
        </div>
        <div class="col-md-4 col-lg-3 ms-auto">
            <!-- Dropdown Sort Dinamis (Options diatur oleh JS tergantung tab aktif) -->
            <select id="sortSelect" class="form-select rounded-pill px-3">
                <option value="due_asc">Deadline Terdekat</option>
                <option value="due_desc">Deadline Terjauh</option>
                <option value="title_asc">Judul (A-Z)</option>
                <option value="title_desc">Judul (Z-A)</option>
            </select>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="courseTabsContent">
        
        <!-- 3. List Container Tab 1 (Daftar Tugas) -->
        <div class="tab-pane fade show active" id="tab-tasks" role="tabpanel" aria-labelledby="tab-tasks-btn">
            <div id="tasks-list-container" class="d-flex flex-column gap-3">
                <!-- Konten Tugas akan dirender oleh JS di sini -->
            </div>
            <div class="text-center mt-3 mb-5">
                <button id="btn-load-more-tasks" class="btn btn-outline-secondary rounded-pill px-4 d-none">Muat Lebih Banyak Tugas...</button>
                <div id="tasks-loading-spinner" class="spinner-border text-primary d-none" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>

        <!-- 4. List Container Tab 2 (Daftar Materi) -->
        <div class="tab-pane fade" id="tab-materials" role="tabpanel" aria-labelledby="tab-materials-btn">
            <div id="materials-list-container" class="d-flex flex-column gap-3">
                <!-- Konten Materi akan dirender oleh JS di sini -->
            </div>
            <div class="text-center mt-3 mb-5">
                <button id="btn-load-more-materials" class="btn btn-outline-secondary rounded-pill px-4 d-none">Muat Lebih Banyak Materi...</button>
                <div id="materials-loading-spinner" class="spinner-border text-primary d-none" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .task-card-item {
        cursor: pointer;
        transition: transform 160ms ease, box-shadow 160ms ease;
    }
    .task-card-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1) !important;
    }
</style>

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
                    
                    <input type="hidden" name="taskType" value="Tugas">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="taskTitle" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="taskTitle" name="taskTitle" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="dueDate" class="form-label">Tenggat Waktu (Deadline) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="dueDate" name="dueDate" required>
                    </div>

                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Deskripsi / Instruksi Tugas</label>
                        <textarea class="form-control" id="taskDescription" name="taskDescription" rows="4" style="resize:none"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="taskAttachments" class="form-label">Lampirkan File Pendukung (Bisa lebih dari 1)</label>
                        <input class="form-control" type="file" id="taskAttachments" name="attachments[]" multiple>
                        <div class="form-text">Maksimal 10 file, total akumulasi ukuran maksimal 15MB (PDF, Word, Excel, PPT, ZIP, dll).</div>
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
                        <label for="materialAttachments" class="form-label">Lampirkan File Materi <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="materialAttachments" name="attachments[]" multiple required>
                        <div class="form-text">Maksimal 10 file, total akumulasi ukuran maksimal 15MB (PDF, Word, Excel, PPT, ZIP, dll).</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="materialTitle" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="materialTitle" name="materialTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="materialDescription" class="form-label">Catatan / Deskripsi Materi</label>
                        <textarea class="form-control" id="materialDescription" name="materialDescription" rows="4" style="resize:none"></textarea>
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