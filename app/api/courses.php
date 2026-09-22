<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    // Ambil info user
    $stmtUser = $pdo->prepare("SELECT roleLevel, majorType, studyProgram, classGroup, batchYear FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $roleLevel   = strtolower($user['roleLevel'] ?? 'keroco');
    $isManager   = in_array($roleLevel, ['primordial', 'sepuh']);

    // ==========================================
    // 1. GET: Ambil Daftar Mata Kuliah
    // ==========================================
    if ($method === 'GET') {
        $semesterId = (int)($_GET['semesterId'] ?? 0);
        
        // Validasi: Pastikan semester ini milik cohort/kelas user tersebut
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM courses c
            JOIN semesters s ON c.semesterId = s.semesterId
            WHERE c.semesterId = :semId 
              AND s.majorType = :major 
              AND s.studyProgram = :prodi 
              AND s.classGroup = :kelas 
              AND s.batchYear = :batch
            ORDER BY c.courseCode ASC
        ");
        $stmt->execute([
            'semId' => $semesterId,
            'major' => $user['majorType'],
            'prodi' => $user['studyProgram'],
            'kelas' => $user['classGroup'],
            'batch' => $user['batchYear']
        ]);
        
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'canManage' => $isManager,
            'data'      => $courses
        ]);
        exit;
    }

    // Proteksi CRUD khusus Manager
    if (!$isManager) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Khusus Primordial dan Sepuh.']);
        exit;
    }

    $inputData = [];
    if ($method === 'PUT' || $method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $inputData = is_array($json) ? $json : [];
    }

    // ==========================================
    // 2. POST: Tambah Mata Kuliah
    // ==========================================
    if ($method === 'POST') {
        $semesterId      = (int)($_POST['semesterId'] ?? 0);
        $courseCode      = trim($_POST['courseCode'] ?? '');
        $courseTitle     = trim($_POST['courseTitle'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $lecturerName    = trim($_POST['lecturerName'] ?? '');
        $lecturerEmail   = trim($_POST['lecturerEmail'] ?? '');
        $lecturerPhone   = trim($_POST['lecturerPhone'] ?? '');
        $backgroundColor = trim($_POST['backgroundColor'] ?? '#3b82f6');

        if ($semesterId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Kode dan Judul Matkul wajib diisi.']);
            exit;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO courses (semesterId, courseCode, courseTitle, description, lecturerName, lecturerEmail, lecturerPhone, backgroundColor, createdByUserId)
            VALUES (:semId, :code, :title, :desc, :lname, :lemail, :lphone, :bg, :created)
        ");
        $stmtInsert->execute([
            'semId'   => $semesterId,
            'code'    => $courseCode,
            'title'   => $courseTitle,
            'desc'    => $description,
            'lname'   => $lecturerName,
            'lemail'  => $lecturerEmail,
            'lphone'  => $lecturerPhone,
            'bg'      => $backgroundColor,
            'created' => $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Mata kuliah berhasil ditambahkan!']);
        exit;
    }

    // ==========================================
    // 3. PUT: Edit Mata Kuliah
    // ==========================================
    if ($method === 'PUT') {
        $courseId        = (int)($inputData['courseId'] ?? 0);
        $courseCode      = trim($inputData['courseCode'] ?? '');
        $courseTitle     = trim($inputData['courseTitle'] ?? '');
        $description     = trim($inputData['description'] ?? '');
        $lecturerName    = trim($inputData['lecturerName'] ?? '');
        $lecturerEmail   = trim($inputData['lecturerEmail'] ?? '');
        $lecturerPhone   = trim($inputData['lecturerPhone'] ?? '');
        $backgroundColor = trim($inputData['backgroundColor'] ?? '#3b82f6');

        if ($courseId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data edit tidak valid.']);
            exit;
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE courses 
            SET courseCode = :code, courseTitle = :title, description = :desc, 
                lecturerName = :lname, lecturerEmail = :lemail, lecturerPhone = :lphone, 
                backgroundColor = :bg 
            WHERE courseId = :id
        ");
        $stmtUpdate->execute([
            'code'   => $courseCode,
            'title'  => $courseTitle,
            'desc'   => $description,
            'lname'  => $lecturerName,
            'lemail' => $lecturerEmail,
            'lphone' => $lecturerPhone,
            'bg'     => $backgroundColor,
            'id'     => $courseId
        ]);

        echo json_encode(['success' => true, 'message' => 'Mata kuliah berhasil diperbarui!']);
        exit;
    }

    // ==========================================
    // 4. DELETE: Hapus Mata Kuliah
    // ==========================================
    if ($method === 'DELETE') {
        $courseId = (int)($inputData['courseId'] ?? 0);

        if ($courseId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Mata Kuliah tidak valid.']);
            exit;
        }

        $stmtDelete = $pdo->prepare("DELETE FROM courses WHERE courseId = :id");
        $stmtDelete->execute(['id' => $courseId]);

        echo json_encode(['success' => true, 'message' => 'Mata kuliah berhasil dihapus!']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}