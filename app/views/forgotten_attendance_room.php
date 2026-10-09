<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';
$pageTitle  = 'Lupa Absensi';
$activeMenu = 'schedule';
ob_start();
?>
<div class="container-fluid p-0" id="lupa-absensi-container">

    <!-- Inactive Room Modal -->
    <div class="modal fade" id="roomInactiveModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="roomInactiveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow rounded-4 p-2 text-center">
                <div class="modal-body">
                    <i class="bi bi-exclamation-circle text-warning fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">Room Tidak Aktif</h6>
                    <p class="text-secondary small mb-4">Fitur lupa absen untuk matkul ini sedang tidak aktif!</p>
                    <a href="<?= BASE_URL ?>schedule" class="btn btn-primary rounded-3 fw-semibold small px-4 py-2">Oke</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow rounded-4 text-center p-3">
                <div class="modal-body p-2">
                    <i class="bi bi-exclamation-triangle text-danger fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">Batalkan Lupa Absensi?</h6>
                    <p class="text-secondary small mb-4">Apakah Anda yakin ingin mencabut nama Anda dari daftar lupa absensi ini?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-danger rounded-pill btn-sm px-3 fw-semibold" id="btnConfirmCancel">Ya, Batalkan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div class="d-flex flex-column gap-2">
            <a href="<?= BASE_URL ?>schedule" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 align-self-start mb-2" title="Kembali ke Jadwal">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="bi bi-clipboard-check me-2 text-danger"></i>Lupa Absensi
                </h4>
                <p class="text-secondary small mb-0" id="laSubtitle">Memuat data mata kuliah...</p>
            </div>
        </div>
        <div class="d-flex gap-2" id="laActionButtons" style="display: none !important;">
            <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" id="btnSalinTeks" title="Salin daftar mahasiswa ke clipboard">
                <i class="bi bi-clipboard me-1"></i>Salin Teks
            </button>
            <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" id="btnBagikanLink" title="Salin link room ini">
                <i class="bi bi-share me-1"></i>Bagikan Link
            </button>
        </div>
    </div>

    <!-- Alert Area -->
    <div id="laAlert"></div>

    <!-- Loading Spinner -->
    <div id="laLoading" class="text-center text-muted py-5">
        <div class="spinner-border text-danger spinner-border-sm me-2" role="status"></div>
        Memuat data room...
    </div>

    <!-- Main Room Content (hidden until loaded) -->
    <div id="laContent" style="display: none;">

        <!-- Course Banner -->
        <div id="laCourseBanner" class="card border-0 shadow-sm rounded-4 mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #b91c1c, #ef4444);">
            <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-2 fw-medium">Room Aktif Hari Ini</span>
                    <h5 class="fw-bold mb-1" id="laCourseName">-</h5>
                    <small class="text-white-50" id="laCourseTime">-</small>
                </div>
                <div class="text-end d-none d-md-block">
                    <i class="bi bi-clipboard-check display-4 text-white opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Lupa Absensi Button -->
        <div class="text-center mb-4">
            <button type="button" class="btn btn-danger btn-lg rounded-pill px-5 fw-bold shadow-sm" id="btnLupaAbsensi" style="min-width: 220px;">
                AKU LUPA ABSEN😭
            </button>
            <p class="text-muted small mt-2 mb-0" id="laRegisteredHint" style="display: none;">
                <i class="bi bi-check-circle-fill text-success me-1"></i>Kamu sudah terdaftar untuk sesi ini.
            </p>
        </div>

        <!-- Student List -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-light d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-people-fill me-2 text-danger"></i>Daftar Mahasiswa yang Lupa Absensi
                </h6>
                <span class="badge bg-danger rounded-pill" id="laStudentCount">0</span>
            </div>
            <div class="card-body p-3" id="laStudentList">
                <div class="text-center text-muted py-4 small">
                    Belum ada mahasiswa yang mendaftar.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/forgotten_attendance_room.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
