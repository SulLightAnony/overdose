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

try {
    // 1. Ambil Profil & Data Akademik User
    $stmtUser = $pdo->prepare("SELECT roleLevel, majorType, studyProgram, classGroup, batchYear FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $roleLevel    = strtolower($user['roleLevel'] ?? '');
    $canManage    = ($roleLevel === 'primordial' || $roleLevel === 'sepuh');
    $majorType    = $user['majorType'] ?? '';
    $studyProgram = $user['studyProgram'] ?? '';
    $classGroup   = $user['classGroup'] ?? '';
    $batchYear    = $user['batchYear'] ?? '';

    $method = $_SERVER['REQUEST_METHOD'];

    // Menangani Override Method via Header / FormData (_method)
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    // 2. GET: Ambil Semua Semester
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
                        SELECT semesterId, semesterNumber, semesterTitle, backgroundColor, isActive
                        FROM semesters
                        WHERE deletionStatus = 0
                            AND majorType = :m
                            AND studyProgram = :p
                            AND classGroup = :c
                            AND batchYear = :b
            ORDER BY semesterNumber ASC
        ");
        $stmt->execute([
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);
        $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($semesters as &$s) {
            $s['isActive'] = (int)($s['isActive'] ?? 0);
        }

        echo json_encode([
            'success'   => true,
            'canManage' => $canManage,
            'data'      => $semesters
        ]);
        exit;
    }

    // Otorisasi Pengelolaan: Hanya Primordial dan Sepuh
    if (!$canManage) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Sepuh/Primordial yang dapat mengelola semester.']);
        exit;
    }

    // 3. POST: Tambah Semester Baru
    if ($method === 'POST') {
        $semesterNumber  = intval($_POST['semesterNumber'] ?? 0);
        $semesterTitle   = trim($_POST['semesterTitle'] ?? '');
        $backgroundColor = trim($_POST['backgroundColor'] ?? '#3b82f6');
        $isActive        = isset($_POST['isActive']) && intval($_POST['isActive']) === 1 ? 1 : 0;

        if ($semesterNumber <= 0 || empty($semesterTitle)) {
            echo json_encode(['success' => false, 'message' => 'Nomor dan Judul semester wajib diisi']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtLockGroup = $pdo->prepare("SELECT semesterId FROM semesters WHERE deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b FOR UPDATE");
        $stmtLockGroup->execute([
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);

        // Keamanan: Jika di-set aktif, menonaktifkan semester lain di kelompok akademik yang sama
        if ($isActive === 1) {
            $stmtDeactivate = $pdo->prepare("
                UPDATE semesters
                SET isActive = 0
                WHERE majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b
            ");
            $stmtDeactivate->execute([
                'm' => $majorType,
                'p' => $studyProgram,
                'c' => $classGroup,
                'b' => $batchYear
            ]);
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO semesters (semesterNumber, semesterTitle, majorType, studyProgram, classGroup, batchYear, backgroundColor, isActive, deletionStatus, createdAt, updatedAt)
            VALUES (:num, :title, :m, :p, :c, :b, :bg, :active, 0, NOW(), NOW())
        ");
        $stmtInsert->execute([
            'num'    => $semesterNumber,
            'title'  => $semesterTitle,
            'm'      => $majorType,
            'p'      => $studyProgram,
            'c'      => $classGroup,
            'b'      => $batchYear,
            'bg'     => $backgroundColor,
            'active' => $isActive
        ]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Semester berhasil ditambahkan']);
        exit;
    }

    // 4. PUT: Update Semester / Set Active Status
    if ($method === 'PUT') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data) {
            $data = $_POST;
        }

        $semesterId      = intval($data['semesterId'] ?? 0);
        $semesterNumber  = isset($data['semesterNumber']) ? intval($data['semesterNumber']) : null;
        $semesterTitle   = isset($data['semesterTitle']) ? trim($data['semesterTitle']) : null;
        $backgroundColor = isset($data['backgroundColor']) ? trim($data['backgroundColor']) : null;
        $isActive        = isset($data['isActive']) ? (intval($data['isActive']) === 1 ? 1 : 0) : null;

        if ($semesterId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Semester ID tidak valid']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtLockGroup = $pdo->prepare("SELECT semesterId FROM semesters WHERE deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b FOR UPDATE");
        $stmtLockGroup->execute([
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);

        // Keamanan: Jika status diubah menjadi aktif (1), nonaktifkan semester lain terlebih dahulu
        // Ambil data lama jika ada atribut yang tidak dikirim
        $stmtOld = $pdo->prepare("SELECT semesterNumber, semesterTitle, backgroundColor, isActive FROM semesters WHERE semesterId = :id AND deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b LIMIT 1");
        $stmtOld->execute([
            'id' => $semesterId,
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Semester tidak ditemukan']);
            exit;
        }

        $num    = $semesterNumber !== null ? $semesterNumber : $oldData['semesterNumber'];
        $title  = $semesterTitle !== null ? $semesterTitle : $oldData['semesterTitle'];
        $bg     = $backgroundColor !== null ? $backgroundColor : $oldData['backgroundColor'];
        $active = $isActive !== null ? $isActive : $oldData['isActive'];

        if ($active === 1) {
            $stmtDeactivate = $pdo->prepare("UPDATE semesters SET isActive = 0 WHERE deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b");
            $stmtDeactivate->execute([
                'm' => $majorType,
                'p' => $studyProgram,
                'c' => $classGroup,
                'b' => $batchYear
            ]);
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE semesters 
            SET semesterNumber = :num, semesterTitle = :title, backgroundColor = :bg, isActive = :active, updatedAt = NOW()
            WHERE semesterId = :id AND deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b
        ");
        $stmtUpdate->execute([
            'num'    => $num,
            'title'  => $title,
            'bg'     => $bg,
            'active' => $active,
            'id'     => $semesterId,
            'm'      => $majorType,
            'p'      => $studyProgram,
            'c'      => $classGroup,
            'b'      => $batchYear
        ]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Semester berhasil diperbarui']);
        exit;
    }

    // 5. DELETE: Hapus Semester (Soft Delete)
    if ($method === 'DELETE') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        $semesterId = intval($data['semesterId'] ?? 0);

        if ($semesterId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Semester ID tidak valid']);
            exit;
        }

        $stmtDelete = $pdo->prepare("UPDATE semesters SET deletionStatus = 1, isActive = 0, updatedAt = NOW() WHERE semesterId = :id AND deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b");
        $stmtDelete->execute([
            'id' => $semesterId,
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);

        if ($stmtDelete->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Semester tidak ditemukan']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Semester berhasil dihapus']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Method tidak didukung']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}