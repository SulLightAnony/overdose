<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Material Detail (Detail Materi Pembelajaran)
 * FILE LOCATION: app/views/material_detail.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) lengkap untuk menampilkan detail dari
 * sebuah materi pembelajaran. Tampilan disamakan dengan detail tugas, namun TANPA 
 * tombol selesai, TANPA daftar mahasiswa selesai, dan TANPA kolom komentar.
 * Fokus utama adalah menyajikan deskripsi materi, informasi pengunggah & editor terakhir,
 * serta daftar multi-file lampiran yang dapat dibuka maupun diunduh langsung.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Layout Wrapper: app/views/layout.php
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - Backend API Endpoint: app/api/materials.php (Dipanggil via AJAX)
 * - Frontend Script Module: public/js/modules/material_detail.js
 * - Parent Navigation: app/views/course_detail.php?id=courseId
 * 
 * STRUCTURE & KOMPONEN UI (UI COMPONENTS):
 * 1. Navigation Header:
 *    - Tombol "Kembali ke Course" (Navigasi kembali ke `course_detail.php?id=courseId`).
 * 
 * 2. Header Card Informasi Materi (Top Section):
 *    - Nama Mata Kuliah & Kode Matkul.
 *    - Judul Materi Pembelajaran.
 *    - Tanggal dan Waktu Pengunggahan.
 * 
 * 3. Card Informasi Pembuat & Riwayat Editor (Author & Last Edit Metadata):
 *    - Avatar Pengunggah, Nama Pengunggah, Role Badge (Keroco/Sepuh/Primordial), Tanggal Unggah.
 *    - Informasi Editor Terakhir (jika `lastEditedByUserId` NOT NULL): 
 *      "Terakhir diubah oleh [Nama Editor] pada [Timestamp]".
 * 
 * 4. Card Deskripsi Materi & Multi-File Lampiran:
 *    - Teks Deskripsi / Catatan Materi (format paragraf/linebreaks).
 *    - Container Daftar File Lampiran (Multi-File Upload Results):
 *      Setiap file terlampir menyediakan 2 tombol aksi terpisah:
 *      * Tombol "Buka": Membuka file di tab baru browser (`target="_blank"`).
 *      * Tombol "Download": Mengunduh file langsung ke perangkat lokal (`download` attribute).
 * 
 * 5. Control Bar Action Buttons (Aksi Pengguna - Semua Role Keroco ke Atas):
 *    - Tombol "Edit Materi": Membuka modal edit materi (Judul, Deskripsi, Tambah File Lampiran).
 *    - Tombol "Hapus Materi": Memicu Soft-Delete baris data database + Hard-Delete file fisik via API `materials.php`.
 * 
 * 6. Modal Form Edit Materi:
 *    - Input Judul Materi, Input Deskripsi/Catatan Materi, serta Input Multi-File Lampiran Baru.
 * 
 * CARA KERJA SCRIPT FRONTEND:
 * - Mengambil ID Materi dari query parameter URL (`?id=X`).
 * - Mengirim request GET ke `app/api/materials.php?action=detail&materialId=X`.
 * - Jika data materi memiliki `deletionStatus = 1` (Soft-Deleted), tampilkan pesan "Materi ini telah dihapus oleh [userName] pada [timestamp]" dan blokir akses konten.
 * - Merender data materi dan daftar file lampiran ke elemen DOM.
 * - Mengelola request AJAX saat user mengklik "Simpan Perubahan" (Edit) atau "Hapus Materi".
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$materialId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = 'Detail Materi Pembelajaran';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="material-detail-container" data-material-id="<?= $materialId ?>">

    <!-- 1. Navigation Header -->
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
        
        <!-- 2. Header Card Informasi Materi -->
        <div class="card shadow-sm mb-4 border-0 border-start border-5 border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                    <div>
                        <span class="badge bg-secondary me-1" id="courseCodeBadge"></span>
                        <span class="badge bg-success text-white">Materi</span>
                    </div>
                    <div class="text-muted small fw-bold" id="materialDateText">
                        <!-- Tanggal unggah akan dirender JS -->
                    </div>
                </div>
                <h2 class="card-title fw-bold mb-1" id="materialTitle"></h2>
                <p class="text-muted mb-0" id="courseNameTitle"></p>
            </div>
        </div>

        <!-- 3. Card Informasi Pembuat & Riwayat Editor -->
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center">
                    <img id="authorAvatar" src="" alt="Avatar Pengunggah" class="rounded-circle me-3 border" style="width: 50px; height: 50px; object-fit: cover;">
                    <div>
                        <h6 class="mb-1 fw-bold" id="authorName"></h6>
                        <small class="text-muted" id="authorRole"></small>
                    </div>
                </div>
                <div class="text-md-end d-none" id="editorContainer">
                    <small class="text-muted fst-italic" id="editorInfo"></small>
                </div>
            </div>
        </div>

        <!-- 4. Card Deskripsi Materi & Multi-File Lampiran -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-3 border-bottom pb-2">Catatan / Deskripsi Materi</h5>
                <div id="materialDescription" class="mb-4" style="white-space: pre-wrap; line-height: 1.6;"></div>

                <h5 class="card-title fw-bold mb-3 border-bottom pb-2">Lampiran File Materi</h5>
                <div id="materialFilesContainer" class="list-group">
                    <!-- Item file akan di-render oleh JS -->
                    <div class="text-muted fst-italic" id="noFilesText">Tidak ada lampiran file.</div>
                </div>
            </div>
        </div>

        <!-- 5. Control Bar Action Buttons -->
        <div class="card shadow-sm mb-4 bg-light d-none" id="actionButtonsContainer">
            <div class="card-body d-flex flex-wrap justify-content-end align-items-center gap-2">
                <button class="btn btn-warning px-4" id="btnEditMaterial" data-bs-toggle="modal" data-bs-target="#editMaterialModal">Edit Materi</button>
                <button class="btn btn-danger px-4" id="btnDeleteMaterial">Hapus Materi</button>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- 6. Modal Form Edit Materi -->
<!-- ============================================================ -->
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formEditMaterial" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMaterialModalLabel">Edit Materi Pembelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <!-- Spoofing method untuk menangani request PUT via multi-part PHP -->
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="materialId" value="<?= $materialId ?>">
                    
                    <div class="mb-3">
                        <label for="editMaterialTitle" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editMaterialTitle" name="materialTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="editMaterialDescription" class="form-label">Catatan / Deskripsi Materi</label>
                        <textarea class="form-control" id="editMaterialDescription" name="materialDescription" rows="5"></textarea>
                    </div>

                    <div class="mb-3 border rounded p-3 bg-light">
                        <label for="editMaterialAttachments" class="form-label fw-bold">Tambahkan File Lampiran Baru (Opsional)</label>
                        <input class="form-control mb-2" type="file" id="editMaterialAttachments" name="attachments[]" multiple>
                        <small class="text-muted d-block">Pilih file tambahan jika diperlukan (mendukung multi-file). File yang sudah ada sebelumnya tidak akan terhapus.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitEditMaterial">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_MATERIAL_ID = <?= $materialId ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/material_detail.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>