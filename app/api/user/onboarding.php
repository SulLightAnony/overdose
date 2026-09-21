<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

// Validasi otorisasi
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login');
    exit;
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'settings?onboarding=true&error=invalid_method');
    exit;
}

// Ambil input dari form
$prodi_id = $_POST['prodi_id'] ?? null;
$angkatan = $_POST['angkatan'] ?? null;
$kelas    = $_POST['kelas'] ?? null;

// Validasi kelengkapan data
if (!$prodi_id || !$angkatan || !$kelas) {
    // Jika ada yang kosong, kembalikan ke halaman pengaturan
    header('Location: ' . BASE_URL . 'settings?onboarding=true&error=missing_data');
    exit;
}

try {
    // 1. Cek validitas prodi_id di database (Mencegah manipulasi Inspect Element)
    $stmt = $pdo->prepare("SELECT nama_prodi FROM prodi_list WHERE id = :id");
    $stmt->execute(['id' => $prodi_id]);
    $prodiData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prodiData) {
        header('Location: ' . BASE_URL . 'settings?onboarding=true&error=invalid_prodi');
        exit;
    }

    $fullProdiName = $prodiData['nama_prodi'];
    $majorType = '';
    $studyProgram = '';

    // 2. Ekstrak majorType (D3, D4, S2) dan studyProgram secara otomatis dari teks
    // Contoh 1: "D-4 Teknik Informatika" -> D4, Teknik Informatika
    // Contoh 2: "S2 - Pemasaran, Inovasi, dan Teknologi" -> S2, Pemasaran, Inovasi, dan Teknologi
    if (preg_match('/^(D-3|D-4|S-2|S2)\s*(?:-\s*)?(.*)$/i', $fullProdiName, $matches)) {
        $majorType = strtoupper(str_replace('-', '', $matches[1])); // Output: D3, D4, atau S2
        $studyProgram = trim($matches[2]);
    } else {
        $majorType = '-';
        $studyProgram = $fullProdiName;
    }

    // 3. Update data user di database
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET majorType = :majorType, 
            studyProgram = :studyProgram, 
            batchYear = :batchYear, 
            classGroup = :classGroup 
        WHERE userId = :userId
    ");
    
    $updateStmt->execute([
        'majorType'    => $majorType,
        'studyProgram' => $studyProgram,
        'batchYear'    => (int) $angkatan,
        'classGroup'   => strtoupper(trim($kelas)),
        'userId'       => $_SESSION['user_id']
    ]);

    // 4. Update data Sesi agar validasi Gatekeeper tembus ke Dashboard
    $_SESSION['major_type']           = $majorType;
    $_SESSION['study_program']        = $studyProgram;
    $_SESSION['batch_year']           = (int) $angkatan;
    $_SESSION['class_group']          = strtoupper(trim($kelas));
    $_SESSION['onboarding_completed'] = true;

    // Arahkan ke Dashboard
    header('Location: ' . BASE_URL . 'dashboard');
    exit;

} catch (PDOException $e) {
    // Tangani error database jika terjadi kegagalan
    error_log("[Overdose Onboarding Error] " . $e->getMessage());
    header('Location: ' . BASE_URL . 'settings?onboarding=true&error=server_error');
    exit;
}