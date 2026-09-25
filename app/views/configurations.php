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

    <div id="alert-container"></div>

    <div class="row">
        <!-- ============================================================ -->
        <!-- KOLOM KIRI: PROFIL & AKUN -->
        <!-- ============================================================ -->
        <div class="col-lg-4 mb-4">
            
            <!-- 1. User Profile Card -->
            <div class="card shadow-sm border-0 border-top border-5 border-primary mb-4">
                <div class="card-body text-center p-4">
                    <div id="profileSkeleton" class="placeholder-glow">
                        <div class="placeholder rounded-circle mb-3" style="width: 100px; height: 100px;"></div>
                        <h5 class="placeholder col-8 rounded mb-2"></h5>
                        <p class="placeholder col-6 rounded"></p>
                    </div>
                    <div id="profileContent" class="d-none">
                        <img src="" id="profileAvatar" class="rounded-circle mb-3 border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;" alt="Avatar">
                        <h4 class="fw-bold mb-1 text-primary" id="profileName">Loading...</h4>
                        <p class="text-muted mb-2" id="profileEmail">loading@polban.ac.id</p>
                        <span class="badge fs-6" id="profileRoleBadge">Role</span>
                    </div>
                </div>
            </div>

            <!-- 2. Preferences & Privacy Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-sliders me-2"></i> Preferensi & Privasi
                </div>
                <div class="card-body">
                    <!-- Tema -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0 fw-bold">Mode Gelap (Dark Mode)</h6>
                            <small class="text-muted">Ubah tampilan antarmuka menjadi gelap.</small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleDarkMode">
                        </div>
                    </div>
                    <hr>
                    <!-- Notifikasi -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0 fw-bold">Izinkan Notifikasi</h6>
                            <small class="text-muted">Terima pengingat H-1 dan info tugas baru.</small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input cursor-pointer" type="checkbox" id="toggleNotifications">
                        </div>
                    </div>
                    <hr>
                    <!-- Mode Anonim -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold text-danger">Mode Anonim</h6>
                            <small class="text-muted">Sembunyikan identitas Anda di daftar penyelesaian tugas.</small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input cursor-pointer border-danger" type="checkbox" id="toggleAnonymous">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Account Actions Card (Danger Zone) -->
            <div class="card shadow-sm border-0 border-start border-5 border-danger">
                <div class="card-header bg-white fw-bold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Zona Bahaya
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>app/api/auth/logout.php" class="btn btn-outline-secondary fw-bold">
                            <i class="bi bi-box-arrow-right me-2"></i> Keluar (Logout)
                        </a>
                        <button class="btn btn-outline-danger fw-bold" id="btnDeleteAccountPrompt" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="bi bi-trash-fill me-2"></i> Hapus Akun Secara Permanen
                        </button>
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
</script>
<script src="<?= BASE_URL ?>public/js/modules/configurations.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>