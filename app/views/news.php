<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Berita';
$activeMenu = 'news';
ob_start();
?>
<div class="container-fluid p-0" id="news-page">

    <!-- Header Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden" style="background: linear-gradient(135deg, #1e293b, #334155);">
        <div class="card-body p-3 p-md-4 text-white d-flex align-items-center gap-3">
            <div class="rounded-4 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 52px; height: 52px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px);">
                <i class="bi bi-newspaper fs-3"></i>
            </div>
            <div>
                <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-1 fw-medium" style="font-size: 0.75rem;">Portal Informasi</span>
                <h5 class="fw-bold mb-0">Papan Berita & Pengumuman</h5>
                <small class="text-white-50">Informasi dan kabar terbaru seputar perkuliahan dan komunitas akademik.</small>
            </div>
        </div>
    </div>

    <!-- Action Bar (Di sebelah kanan di bawah header/banner berita) -->
    <div class="d-flex justify-content-end mb-4">
        <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" id="openNewsForm">
            <i class="bi bi-plus-lg"></i>Tulis Berita
        </button>
    </div>

    <!-- Alert Box -->
    <div id="newsAlert" aria-live="polite"></div>

    <!-- News List Grid -->
    <div id="newsList" class="row g-3">
        <div class="col-12 text-center text-muted py-5 small">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            Memuat berita...
        </div>
    </div>
</div>

<!-- Modal Tulis/Edit Berita -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form id="newsForm" enctype="multipart/form-data">
                <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e293b, #334155);">
                    <h5 class="modal-title fw-bold fs-6" id="newsModalTitle">Tulis Berita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="newsId" name="id">
                    <input type="hidden" id="newsRemoveImage" name="remove_image" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark" for="newsTitle">Judul Berita</label>
                        <input class="form-control rounded-3" id="newsTitle" name="title" maxlength="200" placeholder="Masukkan judul berita..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark" for="newsContent">Isi Berita</label>
                        <textarea class="form-control rounded-3" id="newsContent" name="content" rows="6" placeholder="Tuliskan isi berita atau pengumuman..." style="resize: none;" required></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-dark d-flex align-items-center justify-content-between mb-1" for="newsImageInput">
                            <span><i class="bi bi-image me-1 text-primary"></i>Gambar Berita <span class="text-muted fw-normal">(Opsional)</span></span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">Maks 1 gambar (1 MB)</span>
                        </label>
                        <input type="file" class="form-control rounded-3" id="newsImageInput" name="image" accept="image/jpeg,image/png,image/jpg,image/webp">
                        <div class="form-text small text-muted mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-info-circle me-1"></i>Format: JPG, PNG, WEBP. Maksimal 1 MB per postingan.
                        </div>

                        <!-- Image Preview Box -->
                        <div id="newsImagePreviewContainer" class="mt-2 p-2 border rounded-3 bg-light d-none align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <img id="newsImagePreviewImg" src="" alt="Preview" class="rounded border object-fit-cover flex-shrink-0" style="width: 48px; height: 48px;" referrerpolicy="no-referrer">
                                <div class="min-w-0">
                                    <div class="small fw-semibold text-truncate text-dark" id="newsImagePreviewName">preview.jpg</div>
                                    <div class="text-muted" style="font-size: 0.72rem;" id="newsImagePreviewSize">-</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 flex-shrink-0" id="btnRemoveNewsImage">
                                <i class="bi bi-x-circle me-1"></i>Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top border-light">
                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" id="submitNews">
                        Terbitkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pratinjau Gambar Penuh -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden bg-dark text-white">
            <div class="modal-header border-0 py-2 px-3 d-flex justify-content-between align-items-center bg-dark">
                <h6 class="modal-title fw-semibold text-truncate small mb-0 text-white" id="imagePreviewTitle">Pratinjau Gambar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center" style="min-height: 250px; max-height: 80vh;">
                <img src="" id="imagePreviewImg" class="img-fluid" style="max-height: 80vh; object-fit: contain;" alt="Preview" referrerpolicy="no-referrer">
            </div>
        </div>
    </div>
</div>

<style>
@keyframes newsShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CURRENT_NEWS_USER_ID = <?= (int)$_SESSION['user_id'] ?>;
const CURRENT_NEWS_ROLE = "<?= htmlspecialchars($_SESSION['role_level'] ?? 'Keroco', ENT_QUOTES, 'UTF-8') ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/news.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
