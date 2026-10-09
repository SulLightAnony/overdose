<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle = 'Berita';
$activeMenu = 'news';
ob_start();
?>
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div><h4 class="fw-bold mb-1"><i class="bi bi-newspaper me-2"></i>Berita</h4><p class="text-secondary small mb-0">Informasi dan kabar terbaru komunitas akademik.</p></div>
        <button type="button" class="btn btn-primary" id="openNewsForm">Tulis Berita</button>
    </div>
    <div id="newsAlert" aria-live="polite"></div>
    <div id="newsList" class="row g-3"><div class="col-12 text-muted py-4">Memuat berita...</div></div>
</div>
<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form id="newsForm">
            <div class="modal-header"><h5 class="modal-title" id="newsModalTitle">Tulis Berita</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" id="newsId" name="id">
                <div class="mb-3"><label class="form-label" for="newsTitle">Judul</label><input class="form-control" id="newsTitle" name="title" maxlength="200" required></div>
                <div class="mb-3"><label class="form-label" for="newsContent">Isi Berita</label><textarea class="form-control" id="newsContent" name="content" rows="8" style="resize:none" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary" id="submitNews">Terbitkan</button></div>
        </form>
    </div></div>
</div>
<script>const BASE_URL = "<?= BASE_URL ?>"; const CURRENT_NEWS_USER_ID = <?= (int)$_SESSION['user_id'] ?>; const CURRENT_NEWS_ROLE = "<?= htmlspecialchars($_SESSION['role_level'] ?? 'Keroco', ENT_QUOTES, 'UTF-8') ?>";</script>
<script src="<?= BASE_URL ?>public/js/modules/news.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
