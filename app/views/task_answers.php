<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Task Answers (Sharing Answer Center View)
 * FILE LOCATION: app/views/task_answers.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) khusus untuk Halaman Sharing Jawaban Tugas.
 * Menampilkan daftar seluruh jawaban yang telah dibagikan oleh rekan-rekan mahasiswa 
 * untuk satu tugas tertentu secara transparan/publik (tanpa syarat harus menyelesaikan tugas dulu),
 * serta menyediakan tombol & modal form bagi pengguna untuk membagikan jawabannya sendiri (+1 Poin).
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Layout Wrapper: app/views/layout.php
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - Backend API Endpoint: app/api/task_answers.php (Dipanggil via AJAX)
 * - Frontend Script Module: public/js/modules/task_answers.js
 * - Parent Navigation: app/views/task_detail.php?id=taskId
 * 
 * STRUCTURE & KOMPONEN UI (UI COMPONENTS):
 * 1. Navigation Header:
 *    - Tombol "Kembali ke Detail Tugas" (Navigasi kembali ke `task_detail.php?id=taskId`).
 * 
 * 2. Header Banner / Task Context Info:
 *    - Menampilkan Nama Mata Kuliah & Judul Tugas yang sedang di-share jawabannya.
 *    - Teks Pengantar: "Pusat Sharing Jawaban Tugas - Bebas diakses dan dibagikan oleh siapa saja."
 *    - Tombol Utamanya: "Bagikan Jawaban Saya (+1 Poin)" (Membuka Modal Form Share Jawaban).
 * 
 * 3. List Container Sharing Jawaban (Dynamic Feed via AJAX):
 *    - Setiap Card Jawaban Menampilkan:
 *      * Avatar, Nama Mahasiswa, Role Badge (Keroco/Sepuh/Primordial), dan Timestamp Pengunggahan.
 *      * Judul Jawaban & Catatan / Diskusi Penjelas dari Pembuat Jawaban.
 *      * Container File Lampiran Jawaban (Multi-File Upload Results):
 *        Setiap file terlampir memiliki 2 tombol aksi:
 *        - Tombol "Buka": Membuka file di tab baru browser (`target="_blank"`).
 *        - Tombol "Download": Mengunduh file jawaban langsung (`download` attribute).
 *      * Tombol Aksi Khusus Author (Hanya muncul jika `authorId === currentUserId` atau role Primordial):
 *        - Tombol "Edit Jawaban" (Membuka modal edit postingan jawaban).
 *        - Tombol "Hapus Jawaban" (Memicu FULL HARD-DELETE row & file fisik via API `task_answers.php`).
 * 
 * 4. Empty State Container:
 *    - Tampilan ilustrasi / teks khusus jika belum ada mahasiswa yang membagikan jawaban: 
 *      "Belum ada jawaban yang dibagikan untuk tugas ini. Jadilah yang pertama membagikan jawaban!"
 * 
 * 5. Modal Form Tambah / Edit Share Jawaban:
 *    - Input Judul Jawaban (Misal: "Jawaban Soal 1-5 Versi A" / "Draft Kodingan").
 *    - Input Catatan / Penjelasan Jawaban (Textarea).
 *    - Input Multi-File Lampiran Jawaban (`input type="file" multiple`).
 * 
 * CARA KERJA SCRIPT FRONTEND (`task_answers.js`):
 * - Mengambil ID Tugas dari query parameter URL (`?taskId=X`).
 * - Mengirim request GET ke `app/api/task_answers.php?action=list&taskId=X`.
 * - Merender daftar card jawaban secara dinamis beserta file lampirannya.
 * - Mengelola submit form pengiriman/pembaruan jawaban via AJAX multipart ke API.
 * - Mengelola konfirmasi & eksekusi aksi Full Hard-Delete jika tombol hapus diklik oleh pembuatnya.
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

// Membaca taskId dari URL (mendukung parameter ?taskId= atau fallback ke ?id=)
$taskId = isset($_GET['taskId']) ? intval($_GET['taskId']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$pageTitle = 'Sharing Jawaban Tugas';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="task-answers-container" data-task-id="<?= $taskId ?>">

    <!-- 1. Navigation Header -->
    <div class="mb-3">
        <a href="<?= BASE_URL ?>app/views/task_detail.php?id=<?= $taskId ?>" id="backToTaskBtn" class="btn btn-sm btn-outline-secondary">
            &larr; Kembali ke Detail Tugas
        </a>
    </div>

    <!-- Alert Container -->
    <div id="alert-container"></div>

    <!-- 2. Header Banner / Task Context Info -->
    <div class="card shadow-sm mb-4 border-0 border-start border-5 border-info bg-light">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="card-title fw-bold mb-1 text-info-emphasis">Pusat Sharing Jawaban</h4>
                <p class="text-muted mb-2">Bebas diakses, dilihat, dan dibagikan oleh siapa saja tanpa syarat penyelesaian tugas.</p>
                <div class="fw-bold" id="taskTitleContext">
                    <!-- Judul Tugas akan dirender oleh JS jika diperlukan, atau sekadar teks loading -->
                    <span class="spinner-border spinner-border-sm text-info" role="status" aria-hidden="true"></span> Memuat konteks tugas...
                </div>
            </div>
            <div>
                <button class="btn btn-info text-white fw-bold px-4 py-2 shadow-sm" id="btnShareAnswer" data-bs-toggle="modal" data-bs-target="#answerModal" data-mode="add">
                    <i class="bi bi-cloud-upload me-2"></i>Bagikan Jawaban Saya (+1 Poin)
                </button>
            </div>
        </div>
    </div>

    <!-- Skeleton Loader -->
    <div id="skeleton-loader" class="placeholder-glow">
        <div class="card shadow-sm mb-4"><div class="card-body"><h5 class="placeholder col-4 rounded mb-3"></h5><p class="placeholder col-8 rounded"></p></div></div>
        <div class="card shadow-sm mb-4"><div class="card-body"><h5 class="placeholder col-3 rounded mb-3"></h5><p class="placeholder col-6 rounded"></p></div></div>
    </div>

    <!-- 3 & 4. List Container / Empty State (Dirender oleh JS) -->
    <div id="answersListContainer" class="d-none">
        <!-- Feed Kartu Jawaban Mahasiswa akan dimasukkan ke sini -->
    </div>

    <div id="emptyStateContainer" class="d-none text-center py-5">
        <div class="text-muted mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-folder2-open" viewBox="0 0 16 16">
                <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z"/>
            </svg>
        </div>
        <h5 class="fw-bold text-secondary">Belum ada jawaban yang dibagikan.</h5>
        <p class="text-muted">Jadilah pahlawan pertama yang membagikan jawaban untuk tugas ini!</p>
    </div>

</div>

<!-- ============================================================ -->
<!-- 5. Modal Form Tambah / Edit Share Jawaban -->
<!-- ============================================================ -->
<div class="modal fade" id="answerModal" tabindex="-1" aria-labelledby="answerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formAnswer" enctype="multipart/form-data">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="answerModalLabel">Bagikan Jawaban</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <!-- Method Override untuk kebutuhan Edit (PUT) -->
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="taskId" value="<?= $taskId ?>">
                    <!-- Field ID untuk Edit -->
                    <input type="hidden" name="answerId" id="inputAnswerId" value="">
                    
                    <div class="mb-3">
                        <label for="answerTitle" class="form-label">Judul Jawaban <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="answerTitle" name="answerTitle" placeholder="Misal: Jawaban Soal 1-5 Versi B / Draft Kodingan" required>
                    </div>

                    <div class="mb-3">
                        <label for="answerNotes" class="form-label">Catatan / Penjelasan Singkat (Opsional)</label>
                        <textarea class="form-control" id="answerNotes" name="answerNotes" rows="4" placeholder="Tambahkan konteks, asumsi, atau penjelasan dari jawabanmu..."></textarea>
                    </div>

                    <div class="mb-3 border rounded p-3 bg-light">
                        <label for="answerAttachments" class="form-label fw-bold">Lampirkan File Jawaban <span class="text-danger" id="fileRequiredStar">*</span></label>
                        <input class="form-control mb-2" type="file" id="answerAttachments" name="attachments[]" multiple>
                        <small class="text-muted d-block" id="fileHelpText">Mendukung multi-file (PDF, Word, Script Code, ZIP, Gambar, dll).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white fw-bold" id="btnSubmitAnswer">Simpan & Bagikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_TASK_ID = <?= $taskId ?>;
    const CURRENT_USER_ID = <?= isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0 ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/task_answers.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>