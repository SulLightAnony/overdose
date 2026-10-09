<?php
/**
 * ====================================================================================
 * MODULE: Global View (News & Live Chat)
 * FILE LOCATION: app/views/global.php
 * ====================================================================================
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$pageTitle  = 'Global';
$activeMenu = 'global';
ob_start();
?>
<div class="container-fluid p-0" id="globalPageContainer">

    <!-- Page Header & Tab Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-globe me-2"></i>Global
            </h4>
            <p class="text-secondary small mb-0">Obrolan komunitas dan kabar berita global Overdose.</p>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-pills gap-2 mb-4 bg-white p-2 rounded-4 shadow-sm border border-light d-inline-flex" id="globalTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-semibold small" id="chat-tab" data-bs-toggle="pill" data-bs-target="#chat-pane" type="button" role="tab" aria-controls="chat-pane" aria-selected="true">
                <i class="bi bi-chat-dots-fill me-2"></i>Obrolan Global
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-semibold small" id="news-tab" data-bs-toggle="pill" data-bs-target="#news-pane" type="button" role="tab" aria-controls="news-pane" aria-selected="false">
                <i class="bi bi-newspaper me-2"></i>Berita Global
            </button>
        </li>
    </ul>

    <!-- Alert Area -->
    <div id="globalAlert"></div>

    <!-- Tab Contents -->
    <div class="tab-content" id="globalTabContent">

        <!-- TAB 1: OBROLAN GLOBAL (FULL WIDTH) -->
        <div class="tab-pane fade show active" id="chat-pane" role="tabpanel" aria-labelledby="chat-tab" tabindex="0">
            <div class="card border-0 shadow-sm rounded-4 d-flex flex-column overflow-hidden" style="height: calc(100vh - 230px); min-height: 520px;">
                <!-- Chat Header -->
                <div class="card-header bg-white py-3 px-4 border-bottom border-light d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-chat-dots-fill text-success fs-5"></i>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Live Chat Global</h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Saling menyapa dan berbagi informasi secara langsung</small>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-medium small">
                        <i class="bi bi-circle-fill me-1 text-success" style="font-size: 0.5rem;"></i>Live
                    </span>
                </div>

                <!-- Messages Area -->
                <div class="card-body p-2 px-3 overflow-auto flex-grow-1 bg-light bg-opacity-50 d-flex flex-column" id="chat-messages" style="gap:0;">
                    <div class="text-center text-muted py-5 small">
                        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                        Memuat obrolan...
                    </div>
                </div>

                <!-- Chat Footer / Input -->
                <div class="card-footer bg-white p-3 border-top border-light">
                    <!-- Reply Preview Bar -->
                    <div id="replyPreviewBar" class="d-none align-items-center justify-content-between bg-light border-start border-4 border-primary px-3 py-2 mb-2 rounded-3 small">
                        <div class="text-truncate me-2">
                            <span class="fw-bold text-primary">Membalas ke:</span>
                            <span class="fw-semibold text-dark ms-1" id="replyPreviewSender"></span>
                            <span class="text-muted ms-1">- <span id="replyPreviewText"></span></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0 shadow-none border-0" id="btnCancelReply" title="Batal Balas">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <form id="chat-form" class="d-flex align-items-end gap-2">
                        <div class="flex-grow-1 position-relative">
                            <textarea id="chat-input" class="form-control rounded-4 px-3 py-2 shadow-none border-light bg-light" placeholder="Ketik pesan... YANG RAMAH YAH" rows="1" style="resize: none; max-height: 80px; overflow-y: auto; font-size: 0.9rem;" required></textarea>
                        </div>
                        <button type="button" class="btn btn-light rounded-circle p-0 d-none align-items-center justify-content-center flex-shrink-0 shadow-sm" id="btnCancelEdit" style="width: 42px; height: 42px;" title="Batal Edit">
                            <i class="bi bi-x-lg text-secondary fs-6"></i>
                        </button>
                        <button type="submit" class="btn btn-primary rounded-circle p-0 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm hover-lift" id="btnSendChat" style="width: 42px; height: 42px;" title="Kirim Pesan">
                            <i class="bi bi-send-fill fs-6" id="btnSendChatIcon"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: BERITA GLOBAL (FULL WIDTH) -->
        <div class="tab-pane fade" id="news-pane" role="tabpanel" aria-labelledby="news-tab" tabindex="0">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom border-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-newspaper text-primary me-2 fs-5"></i>Berita Komunitas Global
                        </h6>
                        <small class="text-muted">Kabar dan pengumuman umum untuk seluruh anggota</small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold d-flex align-items-center gap-1 shadow-sm" id="openGlobalNewsForm">
                        <i class="bi bi-plus-lg"></i>Tulis Berita
                    </button>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div id="news-container" class="row g-3">
                        <div class="col-12 text-center text-muted py-5 small">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Memuat berita global...
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Konfirmasi Hapus Chat -->
<div class="modal fade" id="deleteChatConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4 text-center p-3">
            <div class="modal-body p-2">
                <i class="bi bi-exclamation-triangle text-danger fs-1 mb-2 d-block"></i>
                <h6 class="fw-bold text-dark mb-1">Hapus Pesan?</h6>
                <p class="text-secondary small mb-4">Apakah Anda yakin ingin menghapus pesan ini dari obrolan global?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger rounded-pill btn-sm px-3 fw-semibold" id="btnConfirmDeleteChat">Hapus</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Floating Notifikasi Salin Pesan -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="copyToast" class="toast align-items-center text-bg-dark border-0 rounded-3 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body py-2 px-3 small">
                Teks berhasil disalin
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>
    </div>
</div>

<!-- Modal Tulis/Edit Berita Global -->
<div class="modal fade" id="globalNewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form id="globalNewsForm">
                <div class="modal-header bg-primary text-white py-3 px-4">
                    <h5 class="modal-title fw-bold fs-6" id="globalNewsModalTitle">Tulis Berita Global</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="globalNewsId" name="id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark" for="globalNewsTitle">Judul Berita</label>
                        <input class="form-control rounded-3" id="globalNewsTitle" name="title" maxlength="200" placeholder="Masukkan judul berita global..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark" for="globalNewsContent">Isi Berita</label>
                        <textarea class="form-control rounded-3" id="globalNewsContent" name="content" rows="6" placeholder="Tuliskan isi berita atau pengumuman..." style="resize: none;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top border-light">
                    <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill btn-sm px-4 fw-semibold" id="submitGlobalNews">Terbitkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.chat-row {
    position: relative;
    margin-bottom: 0;
    line-height: 1;
}
.chat-row .group-chat-item {
    margin-bottom: 0;
}
.chat-options-dropdown {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity 0.15s ease-in-out;
}
.chat-incoming .chat-options-dropdown {
    margin-right: 0px;
}
.chat-outgoing .chat-options-dropdown {
    margin-left: 0px;
}
.chat-row:hover .chat-options-dropdown,
.chat-row:focus-within .chat-options-dropdown,
.chat-row .chat-options-dropdown.show {
    opacity: 1 !important;
}
@media (hover: none) {
    .chat-options-dropdown {
        opacity: 0.65 !important;
    }
}
.dropdown-toggle.no-arrow::after {
    display: none !important;
}
.chat-options-menu {
    min-width: 140px;
    font-size: 0.875rem !important;
}
.chat-options-menu .dropdown-item {
    font-size: 0.875rem !important;
    padding: 0.35rem 0.85rem !important;
}
.chat-options-menu .dropdown-item i {
    font-size: 1rem !important;
}
#chat-messages {
    padding-top: 10px !important;
    padding-bottom: 10px !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}
.chat-quote-box {
    cursor: pointer;
    background: rgba(0, 0, 0, 0.05);
    border-left: 3px solid #0d6efd;
    padding: 4px 8px;
    border-radius: 6px;
    margin-bottom: 5px;
    font-size: 0.78rem;
    line-height: 1.3;
    transition: opacity 0.15s ease;
}
.chat-outgoing .chat-quote-box {
    background: rgba(255, 255, 255, 0.2);
    border-left: 3px solid #ffffff;
}
.chat-quote-box:hover {
    opacity: 0.85;
}
.chat-meta-info {
    white-space: nowrap;
    line-height: 1;
}
/* Highlight background lebih muda/terang tanpa stroke */
.chat-bubble-editing {
    filter: brightness(1.22) contrast(0.95) !important;
    transition: filter 0.2s ease, background-color 0.2s ease;
}
.chat-target-highlight {
    animation: flashLighterBackground 1.5s ease;
}
@keyframes flashLighterBackground {
    0% {
        filter: brightness(1.25);
        background-color: #f0f7ff !important;
    }
    100% {
        filter: brightness(1);
    }
}
</style>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
const CURRENT_USER_ROLE = "<?= htmlspecialchars($_SESSION['role_level'] ?? 'Keroco', ENT_QUOTES, 'UTF-8') ?>";
document.getElementById("chat-input").focus();
</script>
<script src="<?= BASE_URL ?>public/js/modules/global.js"></script>


<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>

