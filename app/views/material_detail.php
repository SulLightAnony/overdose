<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Material Detail (Detail Materi Pembelajaran)
 * FILE LOCATION: app/views/material_detail.php
 * ====================================================================================
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$materialId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = 'Detail Materi Pembelajaran';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="material-detail-container" data-material-id="<?= $materialId ?>">

    <!-- Navigation Header -->
    <div class="mb-3">
        <a href="#" id="backToCourseBtn" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Alert Container -->
    <div id="alert-container"></div>

    <!-- Skeleton Loader -->
    <div id="skeleton-loader" class="placeholder-glow">
        <div class="card shadow-sm mb-4 rounded-4 border-0 p-4"><div class="card-body"><h3 class="placeholder col-6 rounded mb-3"></h3><p class="placeholder col-4 rounded"></p></div></div>
        <div class="card shadow-sm mb-4 rounded-4 border-0 p-4"><div class="card-body"><p class="placeholder col-8 rounded"></p></div></div>
    </div>

    <!-- Wrapper Konten Utama -->
    <div id="main-content-wrapper" class="d-none">
        
        <!-- 1. Banner Utama Informasi Materi (Desain Konsisten Mirip Course Detail Item Card) -->
        <div class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden mb-4 p-4" style="border-left: 5px solid #10b981 !important;">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div class="flex-grow-1">
                    <div class="text-secondary small fw-semibold mb-1" id="courseNameTitle"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <i class="bi bi-journal-text text-success fs-3 me-1 align-middle"></i>
                        <span class="fs-4 fw-bold text-dark align-middle" id="materialTitle"></span>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 small fw-semibold align-middle">Materi</span>
                    </div>
                </div>

                <!-- Menu Titik Tiga Aksi Banner di Sudut Kanan Atas -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-circle shadow-none p-0 d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 36px; height: 36px;" title="Menu Aksi">
                        <i class="bi bi-three-dots-vertical fs-5 text-secondary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                        <li>
                            <button class="dropdown-item py-2 small" type="button" data-share-url>
                                <i class="bi bi-share me-2"></i>Bagikan URL
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-2 small d-none" type="button" id="btnDropdownEditMaterial" data-bs-toggle="modal" data-bs-target="#editMaterialModal">
                                <i class="bi bi-pencil me-2"></i>Edit Materi
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-2 small text-danger d-none" type="button" id="btnDropdownDeleteMaterial">
                                <i class="bi bi-trash me-2"></i>Hapus Materi
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Informasi Pembuat di Bagian Bawah Banner -->
            <div class="mt-3 pt-2 border-top border-light-subtle text-muted small" id="bannerAuthorInfo"></div>
        </div>

        <!-- 2. Deskripsi Materi & Lampiran File (Satu Wrapper Card) -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Deskripsi Materi</h5>
            <div id="materialDescription" class="text-secondary" style="white-space: pre-wrap; line-height: 1.6;"></div>

            <!-- Pemisah Garis Tipis Antara Deskripsi & Lampiran File -->
            <div id="materialFilesDivider" class="border-top border-light-subtle my-4 d-none"></div>

            <!-- Grid Lampiran File (Tanpa Teks Judul "Lampiran File") -->
            <div id="materialFilesGrid" class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4"></div>
            <div class="text-muted fst-italic d-none" id="noFilesText">Tidak ada lampiran file.</div>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- Modal Form Edit Materi -->
<!-- ============================================================ -->
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <form id="formEditMaterial" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editMaterialModalLabel">Edit Materi Pembelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="materialId" value="<?= $materialId ?>">
                    
                    <div class="mb-3">
                        <label for="editMaterialTitle" class="form-label fw-semibold">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="editMaterialTitle" name="materialTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="editMaterialDescription" class="form-label fw-semibold">Catatan / Deskripsi Materi</label>
                        <textarea class="form-control rounded-3" id="editMaterialDescription" name="materialDescription" rows="5" style="resize:none"></textarea>
                    </div>

                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <label for="editMaterialAttachments" class="form-label fw-bold">Tambahkan File Lampiran Baru (Opsional)</label>
                        <input class="form-control mb-2" type="file" id="editMaterialAttachments" name="attachments[]" multiple>
                        <div class="form-text small">Maksimal 10 file, total akumulasi ukuran maksimal 15MB (PDF, Word, Excel, PPT, ZIP, dll). File lama tidak akan terhapus.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" id="btnSubmitEditMaterial">Simpan Perubahan</button>
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