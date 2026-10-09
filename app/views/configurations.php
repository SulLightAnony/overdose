<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Configurations (Profile Center & System Admin)
 * FILE LOCATION: app/views/configurations.php
 * ====================================================================================
 *
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) terpusat untuk Halaman Configurations & Profile Center.
 * Mengakomodasi tampilan profil pribadi, kontrol preferensi akun (Dark Mode, Toggle Notifikasi,
 * Mode Anonim), aksi area berbahaya (Hapus Akun Mandiri), serta menyediakan Tab Khusus Manajemen
 * Pengguna dan Blacklist Email untuk peran Primordial dan Sepuh dengan dukungan proteksi hierarki.
 *
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Layout Wrapper: app/views/layout.php
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - Backend API Endpoint: app/api/configurations.php (Dipanggil via AJAX)
 * - Frontend Script Module: public/js/modules/configurations.js
 *
 * STRUCTURE & KOMPONEN UI (UI COMPONENTS):
 * 1. User Profile Card:
 *    - Menampilkan Foto Avatar Google, Nama Lengkap, Email Polban, dan Badge Level Role.
 *
 * 2. Preferences & Privacy Card (Toggle Switches):
 *    - Toggle Mode Gelap / Terang (Dark/Light Mode) -> Terhubung ke localStorage & class .dark-theme.
 *    - Toggle Izin Notifikasi -> Mengubah atribut enableNotifications di DB.
 *    - Toggle Mode Anonim (Sembunyikan Identitas) -> Mengubah atribut isAnonymous di DB.
 *
 * 3. Account Actions Card (Danger Zone):
 *    - Tombol Logout.
 *    - Tombol Hapus Akun Mandiri (Hard Delete) -> Memicu modal konfirmasi keamanan ganda.
 *
 * 4. Admin Management Center (Khusus Primordial & Sepuh):
 *    - Tab 1: Manajemen Pengguna (User Management)
 *      * Tabel daftar seluruh pengguna (Nama, Email, Peran, Status).
 *      * Primordial: Dapat mengubah peran (Primordial/Sepuh/Keroco) & memblokir/membuka blokir.
 *      * Sepuh: Dapat melihat list & memblokir Keroco/Sepuh lain (tidak bisa memblokir Primordial atau membuka blokir buatan Primordial).
 *    - Tab 2: Blacklist Email
 *      * Tabel daftar email terblokir.
 *      * Form/Modal Tambah Blacklist Email Baru (Email & Alasan).
 *      * Aksi Hapus Blacklist dengan indikator penguncian hierarki jika ditambahkan oleh Primordial.
 *
 * CARA KERJA SCRIPT FRONTEND:
 * - Mengambil data profil dan preferensi via GET app/api/configurations.php?action=get_profile.
 * - Mengirim AJAX POST saat toggle switch diubah.
 * - Mengelola modal konfirmasi Hard Delete Akun.
 * - Mengelola filter, submit form, dan interaksi tabel admin secara dinamis.
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$pageTitle = 'Configurations & Profile Center';
$activeMenu = 'configurations';
$userRole = $_SESSION['role_level'] ?? 'Keroco';

ob_start();
?>

<div class="container-fluid p-0" id="configurations-container" data-user-role="<?= htmlspecialchars($userRole) ?>">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-gear-wide-connected me-2"></i>Pengaturan & Pusat Profil</h4>
            <p class="text-secondary small mb-0">Kelola preferensi akun, privasi, notifikasi, dan sesi login Anda.</p>
        </div>
    </div>

    <div id="alert-container"></div>

    <div class="row g-4">
        <!-- ============================================================ -->
        <!-- KOLOM KIRI: PROFIL PENGGUNA & ZONA BAHAYA -->
        <!-- ============================================================ -->
        <div class="col-lg-5 col-xl-4">

            <!-- 1. User Profile Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
                <div class="p-4 text-center text-white position-relative" style="background: linear-gradient(135deg, #1e293b, #0f172a) !important;">
                    <div id="profileSkeleton" class="placeholder-glow py-3">
                        <div class="placeholder rounded-circle mb-3 mx-auto d-block" style="width: 90px; height: 90px;"></div>
                        <h5 class="placeholder col-8 rounded mb-2 mx-auto d-block"></h5>
                        <p class="placeholder col-6 rounded mx-auto d-block"></p>
                    </div>
                    <div id="profileContent" class="d-none py-2">
                        <div class="position-relative d-inline-block mb-3">
                            <img src="" id="profileAvatar" class="rounded-circle border border-3 border-white shadow-lg" style="width: 96px; height: 96px; object-fit: cover;" alt="Avatar">
                        </div>
                        <h5 class="fw-bold mb-1 text-white" id="profileName">Loading...</h5>
                        <p class="text-white-50 small mb-2" id="profileEmail">loading@polban.ac.id</p>
                        <span class="badge fs-6 px-3 py-1 rounded-pill" id="profileRoleBadge">Role</span>
                    </div>
                </div>
                <div class="card-body p-3 bg-light text-center border-top">
                    <small class="text-muted"><i class="bi bi-shield-check text-success me-1"></i>Akun Terverifikasi Overdose</small>
                </div>
            </div>

            <!-- 2. Account Actions Card (Danger Zone) -->
            <div class="card shadow-sm border-0 rounded-4 border-start border-4 border-danger">
                <div class="card-header bg-white py-3 fw-bold text-danger border-bottom-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Zona Bahaya & Sesi
                </div>
                <div class="card-body pt-0">
                    <p class="text-muted small mb-3">Tindakan akun yang memerlukan konfirmasi ekstra.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-danger btn-sm py-2 fw-semibold rounded-3 text-start px-3" id="btnDeleteAccountPrompt" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="bi bi-trash3-fill me-2"></i> Hapus Akun Saya Secara Permanen
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- KOLOM KANAN: PREFERENSI & INFORMASI SISTEM -->
        <!-- ============================================================ -->
        <div class="col-lg-7 col-xl-8">

            <!-- 1. Preferences & Privacy Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-sliders me-2 text-primary"></i>Preferensi Tampilan & Privasi
                    </h6>
                </div>
                <div class="card-body p-4">

                    <!-- Dark Mode -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-dark text-white rounded-3 p-2 fs-5">
                                <i class="bi bi-moon-stars-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Mode Gelap (Dark Mode)</h6>
                                <small class="text-secondary">Ubah tema tampilan antarmuka menjadi lebih nyaman di mata.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleDarkMode">
                        </div>
                    </div>

                    <!-- Notifikasi Push -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary text-white rounded-3 p-2 fs-5">
                                <i class="bi bi-bell-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Izinkan Notifikasi</h6>
                                <small class="text-secondary">Terima pemberitahuan pengingat deadline H-1 dan info tugas baru.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleNotifications">
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success text-white rounded-3 p-2 fs-5">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Notifikasi Absensi</h6>
                                <small class="text-secondary">Pengingat saat periode absensi mata kuliah dimulai.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleAttendanceNotifications">
                        </div>
                    </div>

                    <!-- Mode Anonim -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger text-white rounded-3 p-2 fs-5">
                                <i class="bi bi-incognito"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Mode Anonim</h6>
                                <small class="text-secondary">Sembunyikan identitas nama Anda saat menandai penyelesaian tugas.</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input cursor-pointer border-danger" type="checkbox" id="toggleAnonymous">
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: KONFIRMASI HAPUS AKUN (HARD DELETE) -->
<!-- ============================================================ -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-octagon me-2"></i> Hapus Akun Permanen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold text-danger">TINDAKAN INI TIDAK DAPAT DIBATALKAN!</p>
                <p>Apakah Anda yakin ingin menghapus akun Anda? Seluruh akses login Anda akan dicabut selamanya.</p>
                <div class="alert alert-warning small mb-0">
                    <strong>Catatan:</strong> Data akademik (tugas, materi, sharing jawaban) yang pernah Anda buat akan tetap tersimpan di dalam sistem untuk kepentingan akademis angkatan (tanpa nama Anda).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger fw-bold" id="btnConfirmDeleteAccount">Ya, Hapus Akun Saya</button>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_USER_ROLE = "<?= htmlspecialchars($userRole) ?>";
    const PUBLIC_VAPID_KEY = "<?= htmlspecialchars(VAPID_PUBLIC_KEY, ENT_QUOTES, 'UTF-8') ?>";
</script>
<script src="<?= BASE_URL ?>public/js/modules/configurations.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>