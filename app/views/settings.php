<?php
// Gatekeeper Keamanan: Otomatis validasi sesi, cek database, dan redirect logout jika akun dihapus
require_once __DIR__ . '/../api/auth/gatekeeper.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../api/db.php';

// Ambil data Program Studi dari database
$stmt = $pdo->query("SELECT id, jurusan, nama_prodi FROM prodi_list ORDER BY id ASC");
$allProdi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mengelompokkan prodi berdasarkan Jurusan untuk Select Option
$groupedProdi = [];
foreach ($allProdi as $prodi) {
    $groupedProdi[$prodi['jurusan']][] = $prodi;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdose — Lengkapi Profil</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS (Reusing Login Style) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/logo.png" type="image/png">
</head>
<body class="login-body">

    <div class="login-container">
        
        <!-- DIV 1: Logo & Header Description -->
        <div class="onboarding-header text-center w-100">
            <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="brand-logo mb-3" style="max-height: 200px;">
            <h4 class="fw-bold text-dark mb-1 fs-5">Lengkapi Identitas</h4>
            <p class="text-secondary small mb-4">Pilih data akademikmu untuk melanjutkan. Jangan asal dan jangan coba yang aneh-aneh. Kami tahu siapa kamu.</p>
        </div>

        <!-- DIV 2: Form Input & Navigation Buttons -->
        <div class="onboarding-body">
            <form id="onboardingForm" action="<?= BASE_URL ?>app/api/user/onboarding.php" method="POST">
                
                <!-- STEP 1: PRODI -->
                <div class="step-container active" id="step-1">
                    <div class="step-content">
                        <label class="fw-semibold text-dark mb-2 small text-start w-100">Program Studi</label>
                        <select class="form-select form-select-custom" name="prodi_id" id="prodi_id" required>
                            <option value="" selected disabled>Pilih Program Studi</option>
                            <?php foreach ($groupedProdi as $jurusan => $prodis): ?>
                                <optgroup label="<?= htmlspecialchars($jurusan) ?>">
                                    <?php foreach ($prodis as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-dark w-100 py-2 rounded-3 fw-semibold" onclick="nextStep(1, 2)">Selanjutnya</button>
                </div>

                <!-- STEP 2: ANGKATAN -->
                <div class="step-container" id="step-2">
                    <div class="step-content">
                        <label class="fw-semibold text-dark mb-2 small text-start w-100">Tahun Angkatan</label>
                        <select class="form-select form-select-custom" name="angkatan" id="angkatan" required>
                            <option value="" selected disabled>Pilih Angkatan</option>
                            <?php 
                            $currentYear = (int)date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 6; $y--) {
                                echo "<option value=\"$y\">$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light border py-2 rounded-3 fw-semibold w-50" onclick="nextStep(2, 1)">Kembali</button>
                        <button type="button" class="btn btn-dark py-2 rounded-3 fw-semibold w-50" onclick="nextStep(2, 3)">Selanjutnya</button>
                    </div>
                </div>

                <!-- STEP 3: KELAS -->
                <div class="step-container" id="step-3">
                    <div class="step-content">
                        <label class="fw-semibold text-dark mb-2 small text-start w-100">Kelas</label>
                        <select class="form-select form-select-custom" name="kelas" id="kelas" required>
                            <option value="" selected disabled>-- Pilih Kelas --</option>
                            <option value="A">Kelas A</option>
                            <option value="B">Kelas B</option>
                            <option value="C">Kelas C</option>
                            <option value="D">Kelas D</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light border py-2 rounded-3 fw-semibold w-50" onclick="nextStep(3, 2)">Kembali</button>
                        <button type="submit" class="btn btn-dark py-2 rounded-3 fw-semibold w-50">Selesai</button>
                    </div>
                </div>

            </form>
        </div>

        <!-- DIV 3: Help / Contact Text -->
        <div class="onboarding-footer w-100 text-center mt-3">
            <small class="text-muted" style="font-size: 0.75rem;">
                Prodi kamu gak ketemu?<br>Hubungi <a href="mailto:sulthan.faazaa.tif425@polban.ac.id" class="fw-semibold text-dark">Almusayid</a>. Buka dengan Gmail.
            </small>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function nextStep(current, next) {
            if (current === 1) {
                const prodi = document.getElementById('prodi_id').value;
                if (!prodi) {
                    alert('Silakan pilih Program Studi terlebih dahulu.');
                    return;
                }
            } else if (current === 2 && next === 3) {
                const angkatan = document.getElementById('angkatan').value;
                if (!angkatan) {
                    alert('Silakan pilih Tahun Angkatan terlebih dahulu.');
                    return;
                }
            }

            document.getElementById('step-' + current).classList.remove('active');
            document.getElementById('step-' + next).classList.add('active');
        }
    </script>
</body>
</html>