<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Task Detail (Detail Tugas & Discussion Center)
 * FILE LOCATION: app/views/task_detail.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) lengkap untuk menampilkan detail sebuah tugas,
 * mengunduh/membuka multi-file lampiran pendukung, melihat informasi pembuat & editor terakhir,
 * menandai status penyelesaian tugas, melihat daftar teman yang sudah selesai, membaca/menulis
 * komentar publik, serta menyediakan tombol akses menuju Halaman Sharing Jawaban.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Layout Wrapper: app/views/layout.php
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - Backend API Endpoint: app/api/tasks.php (Dipanggil via AJAX)
 * - Frontend Script Module: public/js/modules/task_detail.js
 * - External Navigation View: app/views/task_answers.php?taskId=X (Halaman Sharing Jawaban)
 * 
 * STRUCTURE & KOMPONEN UI (UI COMPONENTS):
 * 1. Navigation & Breadcrumb Header:
 *    - Tombol "Kembali ke Course" (Navigasi kembali ke `course_detail.php?id=courseId`).
 * 
 * 2. Header Card Informasi Tugas (Top Section):
 *    - Memuat Nama Mata Kuliah, Kode Matkul, Tipe Tugas (Teori/Praktek).
 *    - Judul Tugas Lengkap.
 *    - Badge Status Deadline (Tersedia / Selesai / Telat) & Countdown Waktu Tenggat.
 * 
 * 3. Card Informasi Pembuat & Riwayat Editor (Author & Last Edit Metadata):
 *    - Avatar Pembuat, Nama Pembuat, Role Badge (Keroco/Sepuh/Primordial), Tanggal Dibuat.
 *    - Informasi Editor Terakhir (jika `lastEditedByUserId` NOT NULL): 
 *      "Terakhir diubah oleh [Nama Editor] pada [Timestamp]".
 * 
 * 4. Card Deskripsi Tugas & Multi-File Lampiran:
 *    - Teks Deskripsi Penuh / Instruksi Tugas (format paragraf/linebreaks).
 *    - Container Daftar File Lampiran (Multi-File Upload Results):
 *      Setiap file terlampir memiliki 2 tombol aksi terpisah:
 *      * Tombol "Buka": Membuka file di tab baru browser (`target="_blank"`).
 *      * Tombol "Download": Mengunduh file langsung ke perangkat lokal (`download` attribute).
 * 
 * 5. Control Bar Action Buttons (Aksi Pengguna):
 *    - Tombol "Tandai Selesai" / "Batal Selesai" (Toggle status via AJAX ke `tasks.php?action=toggle_completion`).
 *    - Tombol "Share Jawaban": Navigasi langsung ke `task_answers.php?taskId=X` (bisa diakses oleh siapa saja tanpa syarat selesai tugas).
 *    - Tombol "Edit Tugas": Membuka modal edit tugas (Dapat diakses oleh semua level user Keroco ke atas).
 *    - Tombol "Hapus Tugas": Memicu Soft-Delete baris data database + Hard-Delete file fisik via API `tasks.php`.
 * 
 * 6. Card Daftar "Mahasiswa yang Telah Selesai":
 *    - Menampilkan daftar pengguna dari `task_completions`.
 *    - Komponen Item: Avatar Pengguna, Nama Pengguna, Badge Level, Waktu Penyelesaian.
 *    - ATURAN PRIVASI (Anonymous Identity):
 *      Jika user mengaktifkan `hideCompletedIdentity = 1`, sistem merender Avatar Default & Nama "Mahasiswa Rahasia".
 *    - Anonymous Note Text di bagian bawah: "Tidak ingin namamu muncul di sini? Atur melalui [Pengaturan Profil]."
 * 
 * 7. Card Komentar Publik (Diskusi Tugas):
 *    - Thread daftar komentar dari `task_comments` (Avatar, Nama, Level, Waktu, Isi Komentar).
 *    - Form Input Teks Komentar Publik + Tombol "Kirim Komentar".
 * 
 * CARA KERJA SCRIPT FRONTEND (`task_detail.js`):
 * - Mengambil ID Tugas dari query parameter URL (`?id=X`).
 * - Mengirim request GET ke `app/api/tasks.php?action=detail&taskId=X`.
 * - Jika data tugas memiliki `deletionStatus = 1` (Soft-Deleted), tampilkan pesan "Tugas ini telah dihapus oleh [userName] pada [timestamp]" dan blokir akses konten.
 * - Merender seluruh data tugas, file lampiran, completions, dan komentar ke DOM.
 * - Mengelola request POST AJAX saat user mengklik "Tandai Selesai", "Kirim Komentar", "Edit Tugas", atau "Hapus Tugas".
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = 'Detail Tugas';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="task-detail-container" data-task-id="<?= $taskId ?>">

    <!-- 1. Navigation & Breadcrumb Header -->
    <div class="mb-3">
        <a href="#" id="backToCourseBtn" class="btn btn-sm btn-outline-secondary">
            &larr; Kembali ke Course
        </a>
    </div>

    <!-- Alert Container (Untuk pesan Soft-Delete/Error) -->
    <div id="alert-container"></div>

    <!-- Skeleton Loader (Ditampilkan saat AJAX memuat data) -->
    <div id="skeleton-loader" class="placeholder-glow">
        <div class="card shadow-sm mb-4"><div class="card-body p-4"><h3 class="placeholder col-6 rounded mb-3"></h3><p class="placeholder col-4 rounded"></p></div></div>
        <div class="card shadow-sm mb-4"><div class="card-body p-4"><p class="placeholder col-8 rounded"></p></div></div>
    </div>

    <!-- Wrapper Konten Utama (Disembunyikan sampai data berhasil dimuat) -->
    <div id="main-content-wrapper" class="d-none">
        
        <!-- 2. Header Card Informasi Tugas -->
        <div class="card shadow-sm mb-4 border-0 border-start border-5 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                    <div>
                        <span class="badge bg-secondary me-1" id="courseCodeBadge"></span>
                        <span class="badge bg-info text-dark" id="taskTypeBadge"></span>
                    </div>
                    <div id="taskStatusBadgeContainer">
                        <!-- Badge status akan dirender JS -->
                    </div>
                </div>
                <h2 class="card-title fw-bold mb-1" id="taskTitle"></h2>
                <p class="text-muted mb-3" id="courseNameTitle"></p>
                <div class="text-danger fw-bold d-flex align-items-center" id="taskDeadlineText">
                    <!-- Deadline teks akan dirender JS -->
                </div>
            </div>
        </div>

        <!-- 3. Card Informasi Pembuat & Riwayat Editor -->
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center">
                    <img id="authorAvatar" src="" alt="Avatar Pembuat" class="rounded-circle me-3 border" style="width: 50px; height: 50px; object-fit: cover;">
                    <div>
                        <h6 class="mb-1 fw-bold" id="authorName"></h6>
                        <small class="text-muted" id="authorRoleDate"></small>
                    </div>
                </div>
                <div class="text-md-end d-none" id="editorContainer">
                    <small class="text-muted fst-italic" id="editorInfo"></small>
                </div>
            </div>
        </div>

        <!-- 4. Card Deskripsi Tugas & Multi-File Lampiran -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-3 border-bottom pb-2">Deskripsi Tugas</h5>
                <div id="taskDescription" class="mb-4" style="white-space: pre-wrap; line-height: 1.6;"></div>

                <h5 class="card-title fw-bold mb-3 border-bottom pb-2">Lampiran File</h5>
                <div id="taskFilesContainer" class="list-group">
                    <!-- Item file akan di-render oleh JS -->
                    <div class="text-muted fst-italic" id="noFilesText">Tidak ada lampiran file.</div>
                </div>
            </div>
        </div>

        <!-- 5. Control Bar Action Buttons -->
        <div class="card shadow-sm mb-4 bg-light">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success fw-bold px-4" id="btnToggleComplete">
                        <span id="completeBtnText">Tandai Selesai</span>
                    </button>
                    <a href="<?= BASE_URL ?>app/views/task_answers.php?taskId=<?= $taskId ?>" class="btn btn-primary fw-bold px-4" id="btnShareJawaban">
                        Share / Lihat Jawaban
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-warning px-4" id="btnEditTask" data-bs-toggle="modal" data-bs-target="#editTaskModal">Edit</button>
                    <button class="btn btn-danger px-4" id="btnDeleteTask">Hapus</button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 6. Card Daftar "Mahasiswa yang Telah Selesai" -->
            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold py-3">
                        Mahasiswa yang Telah Selesai
                        <span class="badge bg-success float-end" id="completionCountBadge">0</span>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <ul class="list-group list-group-flush" id="completionsList">
                            <!-- Item mahasiswa akan dirender oleh JS -->
                        </ul>
                    </div>
                    <div class="card-footer bg-light text-center text-muted" style="font-size: 0.8rem;">
                        Tidak ingin namamu muncul di sini? Atur melalui Pengaturan Profil.
                    </div>
                </div>
            </div>

            <!-- 7. Card Komentar Publik (Diskusi Tugas) -->
            <div class="col-lg-7 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold py-3">
                        Komentar & Diskusi Publik
                    </div>
                    <div class="card-body d-flex flex-column">
                        
                        <!-- Form Input Komentar -->
                        <form id="commentForm" class="mb-4">
                            <div class="mb-2">
                                <textarea class="form-control" id="commentContent" rows="3" placeholder="Tulis komentar, pertanyaan, atau diskusi di sini..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm px-4" id="btnSubmitComment">Kirim Komentar</button>
                            </div>
                        </form>
                        
                        <!-- Container List Komentar -->
                        <div id="commentsContainer" class="d-flex flex-column gap-3 overflow-auto" style="max-height: 500px; padding-right: 10px;">
                            <!-- Item komentar akan dirender oleh JS -->
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- Modal Form Edit Tugas -->
<!-- ============================================================ -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formEditTask" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskModalLabel">Edit Tugas Kuliah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <!-- Spoofing method untuk menangani request PUT via multi-part PHP -->
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="taskId" value="<?= $taskId ?>">
                    <input type="hidden" name="courseId" id="editCourseId" value="">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="editTaskTitle" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editTaskTitle" name="taskTitle" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editTaskType" class="form-label">Tipe Tugas</label>
                            <select class="form-select" id="editTaskType" name="taskType">
                                <option value="Teori">Teori</option>
                                <option value="Praktek">Praktek</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editDueDate" class="form-label">Tenggat Waktu (Deadline) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="editDueDate" name="dueDate" required>
                    </div>

                    <div class="mb-3">
                        <label for="editTaskDescription" class="form-label">Deskripsi / Instruksi Tugas</label>
                        <textarea class="form-control" id="editTaskDescription" name="taskDescription" rows="5"></textarea>
                    </div>

                    <div class="mb-3 border rounded p-3 bg-light">
                        <label for="editTaskAttachments" class="form-label fw-bold">Tambahkan File Lampiran Baru (Opsional)</label>
                        <input class="form-control mb-2" type="file" id="editTaskAttachments" name="attachments[]" multiple>
                        <small class="text-muted d-block">Pilih file tambahan jika diperlukan (mendukung multi-file). File yang sudah ada sebelumnya tidak akan terhapus.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitEditTask">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_TASK_ID = <?= $taskId ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/task_detail.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>