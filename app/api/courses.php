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
    $stmtUser = $pdo->prepare("SELECT roleLevel FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $roleLevel = strtolower($user['roleLevel'] ?? 'keroco');
    $isManager = in_array($roleLevel, ['primordial', 'sepuh']);

    // ==========================================
    // 1. GET: Ambil Daftar Mata Kuliah
    // ==========================================
    if ($method === 'GET') {
        $semesterId = (int)($_GET['semesterId'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM courses c
            JOIN semesters s ON c.semesterId = s.semesterId
            WHERE c.semesterId = :semId AND s.deletionStatus = 0
            ORDER BY c.courseCode ASC
        ");
        $stmt->execute(['semId' => $semesterId]);
        
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'canManage' => $isManager,
            'data'      => $courses
        ]);
        exit;
    }

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
        $semesterId        = (int)($_POST['semesterId'] ?? 0);
        $courseCode        = trim($_POST['courseCode'] ?? '');
        $courseTitle       = trim($_POST['courseTitle'] ?? '');
        $courseType        = trim($_POST['courseType'] ?? 'Teori');
        $courseDescription = trim($_POST['courseDescription'] ?? '');
        $lecturerName      = trim($_POST['lecturerName'] ?? '');
        $lecturerEmail     = trim($_POST['lecturerEmail'] ?? '');
        $lecturerPhone     = trim($_POST['lecturerPhone'] ?? '');
        $backgroundColor   = trim($_POST['backgroundColor'] ?? '#10b981');

        if ($semesterId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Kode dan Judul Matkul wajib diisi.']);
            exit;
        }

        if (!in_array($courseType, ['Teori', 'Praktek'])) {
            $courseType = 'Teori';
        }

        // Ambil data pendukung semester
        $stmtSem = $pdo->prepare("SELECT semesterNumber, majorType, studyProgram, classGroup, batchYear FROM semesters WHERE semesterId = :semId LIMIT 1");
        $stmtSem->execute(['semId' => $semesterId]);
        $sem = $stmtSem->fetch(PDO::FETCH_ASSOC);

        if (!$sem) {
            echo json_encode(['success' => false, 'message' => 'Data semester tidak ditemukan.']);
            exit;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO courses (
                semesterId, semesterNumber, courseCode, courseTitle, courseType, 
                courseDescription, lecturerName, lecturerEmail, lecturerPhone, 
                backgroundColor, majorType, studyProgram, classGroup, batchYear
            ) VALUES (
                :semId, :semNum, :code, :title, :type, 
                :desc, :lname, :lemail, :lphone, 
                :bg, :major, :prodi, :kelas, :batch
            )
        ");
        $stmtInsert->execute([
            'semId'   => $semesterId,
            'semNum'  => $sem['semesterNumber'],
            'code'    => $courseCode,
            'title'   => $courseTitle,
            'type'    => $courseType,
            'desc'    => $courseDescription,
            'lname'   => $lecturerName,
            'lemail'  => $lecturerEmail,
            'lphone'  => $lecturerPhone,
            'bg'      => $backgroundColor,
            'major'   => $sem['majorType'],
            'prodi'   => $sem['studyProgram'],
            'kelas'   => $sem['classGroup'],
            'batch'   => $sem['batchYear']
        ]);

        echo json_encode(['success' => true, 'message' => 'Mata kuliah berhasil ditambahkan!']);
        exit;
    }

    // ==========================================
    // 3. PUT: Edit Mata Kuliah
    // ==========================================
    if ($method === 'PUT') {
        $courseId          = (int)($inputData['courseId'] ?? 0);
        $courseCode        = trim($inputData['courseCode'] ?? '');
        $courseTitle       = trim($inputData['courseTitle'] ?? '');
        $courseType        = trim($inputData['courseType'] ?? 'Teori');
        $courseDescription = trim($inputData['courseDescription'] ?? '');
        $lecturerName      = trim($inputData['lecturerName'] ?? '');
        $lecturerEmail     = trim($inputData['lecturerEmail'] ?? '');
        $lecturerPhone     = trim($inputData['lecturerPhone'] ?? '');
        $backgroundColor   = trim($inputData['backgroundColor'] ?? '#10b981');

        if ($courseId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data edit tidak valid.']);
            exit;
        }

        if (!in_array($courseType, ['Teori', 'Praktek'])) {
            $courseType = 'Teori';
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE courses 
            SET courseCode = :code, 
                courseTitle = :title, 
                courseType = :type,
                courseDescription = :desc, 
                lecturerName = :lname, 
                lecturerEmail = :lemail, 
                lecturerPhone = :lphone, 
                backgroundColor = :bg 
            WHERE courseId = :id
        ");
        $stmtUpdate->execute([
            'code'   => $courseCode,
            'title'  => $courseTitle,
            'type'   => $courseType,
            'desc'   => $courseDescription,
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

        $pdo->beginTransaction();

        $stmtDelTasks = $pdo->prepare("DELETE FROM tasks WHERE courseId = :id");
        $stmtDelTasks->execute(['id' => $courseId]);

        $stmtDelete = $pdo->prepare("DELETE FROM courses WHERE courseId = :id");
        $stmtDelete->execute(['id' => $courseId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Mata kuliah dan seluruh tugasnya berhasil dihapus!']);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}