<?php
/**
 * ====================================================================================
 * MODULE: Traktir View (Donation / Support Developer)
 * FILE LOCATION: app/views/traktir.php
 * ====================================================================================
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$pageTitle  = 'Traktir Developer';
$activeMenu = 'traktir';
ob_start();
?>
<div class="container-fluid p-0" id="traktirPageContainer">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-cup-hot-fill me-2 text-primary"></i>Traktir Developer
            </h4>
            <p class="text-secondary small mb-0">Traktir developer beberapa rupiah untuk membantunya membayar hosting server bulanan! (BTW OPSIONAL GUYS)</p>
        </div>
    </div>

    <!-- Main Card Content -->
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4 p-md-5">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-qr-code-scan fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Scan QRIS</h5>
                    <p class="text-muted small mb-0">Dukung keberlangsungan server aplikasi Overdose</p>
                </div>

                <!-- QRIS Image Display -->
                <div class="d-flex justify-content-center mb-3">
                    <img src="<?= BASE_URL ?>public/assets/img/qris.png" 
                         alt="QRIS Traktir Developer" 
                         class="img-fluid rounded-4 shadow-sm border border-light" 
                         style="max-width: 340px; width: 100%; object-fit: contain;"
                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>public/assets/img/logo.png';">
                </div>

                <!-- Tombol Unduh QRIS -->
                <div class="mb-4">
                    <a href="<?= BASE_URL ?>public/assets/img/qris.png" download="qris-traktir-overdose.png" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold">
                        <i class="bi bi-download"></i> Unduh QRIS
                    </a>
                </div>

                <div class="bg-light p-3 rounded-3 text-secondary small">
                    Terima kasih banyak atas apresiasi dan dukungan Anda untuk membantu server tetap beroperasi.
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Oiia Cat Floating Easter Egg -->
<div id="oiiaCatContainer" class="oiia-cat-container" title="Oiia Oiia!">
    <img id="oiiaCatIdle" src="<?= BASE_URL ?>public/assets/cat/cat-idle.png" alt="Oiia Cat Idle" class="oiia-cat-img active">
    <img id="oiiaCatSlow" src="<?= BASE_URL ?>public/assets/cat/cat-spin-slow.gif" alt="Oiia Cat Spin Slow" class="oiia-cat-img">
    <img id="oiiaCatMedium" src="<?= BASE_URL ?>public/assets/cat/cat-spin-medium.gif" alt="Oiia Cat Spin Medium" class="oiia-cat-img">
    <img id="oiiaCatFast" src="<?= BASE_URL ?>public/assets/cat/cat-spin-fast.gif" alt="Oiia Cat Spin Fast" class="oiia-cat-img">
    <audio id="oiiaCatAudio" src="<?= BASE_URL ?>public/assets/cat/cat-song.mp3" preload="auto"></audio>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/traktir.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
