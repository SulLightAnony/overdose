<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Tangkap override _method untuk form-data / AJAX
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    // Ambil data profil & role user
    $stmtUser = $pdo->prepare("SELECT roleLevel, majorType, studyProgram, classGroup, batchYear FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $roleLevel   = strtolower($user['roleLevel'] ?? 'keroco');
    $isManager   = in_array($roleLevel, ['primordial', 'sepuh']);
    $majorType   = $user['majorType'] ?? '';
    $studyProgram= $user['studyProgram'] ?? '';
    $classGroup  = $user['classGroup'] ?? '';
    $batchYear   = $user['batchYear'] ?? '';

    // ==========================================
    // 1. GET: Ambil Daftar Semester Terfilter
    // ==========================================
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT semesterId, semesterNumber, semesterTitle, backgroundColor, createdAt
            FROM semesters
            WHERE majorType = :majorType 
              AND studyProgram = :studyProgram 
              AND classGroup = :classGroup 
              AND batchYear = :batchYear
            ORDER BY semesterNumber ASC
        ");
        $stmt->execute([
            'majorType'    => $majorType,
            'studyProgram' => $studyProgram,
            'classGroup'   => $classGroup,
            'batchYear'    => $batchYear
        ]);
        $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'canManage' => $isManager,
            'data'      => $semesters
        ]);
        exit;
    }

    // Hanya Primordial dan Sepuh yang boleh CRUD
    if (!$isManager) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Khusus Primordial dan Sepuh.']);
        exit;
    }

    // Input Parsing untuk PUT/DELETE (JSON / Raw Payload)
    $inputData = [];
    if ($method === 'PUT' || $method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $inputData = $json;
        } else {
            parse_str($raw, $inputData);
        }
    }

    // ==========================================
    // 2. POST: Tambah Semester Baru
    // ==========================================
    if ($method === 'POST') {
        $semesterNumber  = (int)($_POST['semesterNumber'] ?? 0);
        $semesterTitle   = trim($_POST['semesterTitle'] ?? '');
        $backgroundColor = trim($_POST['backgroundColor'] ?? '#3b82f6');

        if ($semesterNumber <= 0 || empty($semesterTitle)) {
            echo json_encode(['success' => false, 'message' => 'Nomor semester dan judul wajib diisi.']);
            exit;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO semesters (semesterNumber, semesterTitle, backgroundColor, majorType, studyProgram, classGroup, batchYear, createdByUserId)
            VALUES (:num, :title, :bg, :major, :prodi, :kelas, :batch, :created)
        ");
        $stmtInsert->execute([
            'num'     => $semesterNumber,
            'title'   => $semesterTitle,
            'bg'      => $backgroundColor,
            'major'   => $majorType,
            'prodi'   => $studyProgram,
            'kelas'   => $classGroup,
            'batch'   => $batchYear,
            'created' => $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Semester berhasil ditambahkan!']);
        exit;
    }

    // ==========================================
    // 3. PUT: Edit Semester
    // ==========================================
    if ($method === 'PUT') {
        $semesterId      = (int)($inputData['semesterId'] ?? $_POST['semesterId'] ?? 0);
        $semesterNumber  = (int)($inputData['semesterNumber'] ?? $_POST['semesterNumber'] ?? 0);
        $semesterTitle   = trim($inputData['semesterTitle'] ?? $_POST['semesterTitle'] ?? '');
        $backgroundColor = trim($inputData['backgroundColor'] ?? $_POST['backgroundColor'] ?? '#3b82f6');

        if ($semesterId <= 0 || $semesterNumber <= 0 || empty($semesterTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data edit semester tidak valid.']);
            exit;
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE semesters 
            SET semesterNumber = :num, semesterTitle = :title, backgroundColor = :bg 
            WHERE semesterId = :id
        ");
        $stmtUpdate->execute([
            'num'   => $semesterNumber,
            'title' => $semesterTitle,
            'bg'    => $backgroundColor,
            'id'    => $semesterId
        ]);

        echo json_encode(['success' => true, 'message' => 'Semester berhasil diperbarui!']);
        exit;
    }

    // ==========================================
    // 4. DELETE: Hapus Semester
    // ==========================================
    if ($method === 'DELETE') {
        $semesterId = (int)($inputData['semesterId'] ?? $_GET['id'] ?? 0);

        if ($semesterId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Semester tidak valid.']);
            exit;
        }

        $stmtDelete = $pdo->prepare("DELETE FROM semesters WHERE semesterId = :id");
        $stmtDelete->execute(['id' => $semesterId]);

        echo json_encode(['success' => true, 'message' => 'Semester berhasil dihapus!']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}