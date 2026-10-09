<?php
require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/DriveManager.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // 1. Ambil Profil, Role & Data Akademik User dari Database
    $stmtUser = $pdo->prepare("SELECT userId, userName, roleLevel, majorType, studyProgram, classGroup, batchYear, userStatus FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan atau sesi telah berakhir.']);
        exit;
    }

    if (($user['userStatus'] ?? '') === 'blocked') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akun Anda telah diblokir.']);
        exit;
    }

    $roleLevel    = strtolower($user['roleLevel'] ?? '');
    $canManage    = in_array($roleLevel, ['primordial', 'sepuh'], true);
    $userName     = trim($user['userName'] ?? '');
    $majorType    = $user['majorType'] ?? '';
    $studyProgram = $user['studyProgram'] ?? '';
    $classGroup   = $user['classGroup'] ?? '';
    $batchYear    = $user['batchYear'] ?? '';

    $method = $_SERVER['REQUEST_METHOD'];

    // Menangani Override Method via Header / FormData (_method)
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    // 2. GET: Ambil Semua Semester Aktif & Non-Aktif (yg belum dihapus)
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

    // =========================================================================
    // Otorisasi Pengelolaan Sisi Server (RBAC Enforcement)
    // HANYA Primordial dan Sepuh yang dapat melakukan POST, PUT, atau DELETE
    // =========================================================================
    if (!$canManage) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Sepuh dan Primordial yang memiliki izin mengelola semester.']);
        exit;
    }

    // 3. POST: Tambah Semester Baru (Audit & Sanitasi Input Ketat)
    if ($method === 'POST') {
        $semesterNumber  = filter_var($_POST['semesterNumber'] ?? null, FILTER_VALIDATE_INT);
        $semesterTitle   = trim(strip_tags((string)($_POST['semesterTitle'] ?? '')));
        $backgroundColor = trim((string)($_POST['backgroundColor'] ?? '#3b82f6'));
        $isActive        = (isset($_POST['isActive']) && (int)$_POST['isActive'] === 1) ? 1 : 0;

        if ($semesterNumber === false || $semesterNumber < 1 || $semesterNumber > 14) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nomor semester harus berupa angka antara 1 sampai 14.']);
            exit;
        }

        if (mb_strlen($semesterTitle) < 1 || mb_strlen($semesterTitle) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Judul semester wajib diisi (maksimal 100 karakter).']);
            exit;
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor)) {
            $backgroundColor = '#3b82f6';
        }

        $pdo->beginTransaction();

        $stmtLockGroup = $pdo->prepare("SELECT semesterId FROM semesters WHERE deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b FOR UPDATE");
        $stmtLockGroup->execute([
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);

        // Jika di-set aktif, menonaktifkan semester lain di kelompok akademik yang sama
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
            INSERT INTO semesters (
                semesterNumber, semesterTitle, majorType, studyProgram, classGroup, batchYear,
                backgroundColor, isActive, deletionStatus, createdByUserId, createdAt, updatedAt
            ) VALUES (
                :num, :title, :m, :p, :c, :b,
                :bg, :active, 0, :createdById, NOW(), NOW()
            )
        ");
        $stmtInsert->execute([
            'num'         => $semesterNumber,
            'title'       => $semesterTitle,
            'm'           => $majorType,
            'p'           => $studyProgram,
            'c'           => $classGroup,
            'b'           => $batchYear,
            'bg'          => $backgroundColor,
            'active'      => $isActive,
            'createdById' => $userId
        ]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Semester berhasil ditambahkan!']);
        exit;
    }

    // 4. PUT: Update Semester / Set Active Status
    if ($method === 'PUT') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            $data = $_POST;
        }

        $semesterId      = filter_var($data['semesterId'] ?? null, FILTER_VALIDATE_INT);
        $semesterNumber  = isset($data['semesterNumber']) ? filter_var($data['semesterNumber'], FILTER_VALIDATE_INT) : null;
        $semesterTitle   = isset($data['semesterTitle']) ? trim(strip_tags((string)$data['semesterTitle'])) : null;
        $backgroundColor = isset($data['backgroundColor']) ? trim((string)$data['backgroundColor']) : null;
        $isActive        = isset($data['isActive']) ? ((int)$data['isActive'] === 1 ? 1 : 0) : null;

        if ($semesterId === false || $semesterId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Semester ID tidak valid.']);
            exit;
        }

        if ($semesterNumber !== null && ($semesterNumber < 1 || $semesterNumber > 14)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nomor semester harus antara 1 sampai 14.']);
            exit;
        }

        if ($semesterTitle !== null && (mb_strlen($semesterTitle) < 1 || mb_strlen($semesterTitle) > 100)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Judul semester wajib diisi (maksimal 100 karakter).']);
            exit;
        }

        if ($backgroundColor !== null && !preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor)) {
            $backgroundColor = '#3b82f6';
        }

        $pdo->beginTransaction();

        $stmtLockGroup = $pdo->prepare("SELECT semesterId FROM semesters WHERE deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b FOR UPDATE");
        $stmtLockGroup->execute([
            'm' => $majorType,
            'p' => $studyProgram,
            'c' => $classGroup,
            'b' => $batchYear
        ]);

        $stmtOld = $pdo->prepare("SELECT semesterNumber, semesterTitle, backgroundColor, isActive FROM semesters WHERE semesterId = :id AND deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b LIMIT 1");
        $stmtOld->execute([
            'id' => $semesterId,
            'm'  => $majorType,
            'p'  => $studyProgram,
            'c'  => $classGroup,
            'b'  => $batchYear
        ]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Semester tidak ditemukan atau Anda tidak memiliki akses.']);
            exit;
        }

        $num    = $semesterNumber !== null ? $semesterNumber : $oldData['semesterNumber'];
        $title  = $semesterTitle !== null ? $semesterTitle : $oldData['semesterTitle'];
        $bg     = $backgroundColor !== null ? $backgroundColor : $oldData['backgroundColor'];
        $active = $isActive !== null ? $isActive : (int)$oldData['isActive'];

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

        echo json_encode(['success' => true, 'message' => 'Semester berhasil diperbarui!']);
        exit;
    }

    // 5. DELETE: Hapus Semester (Soft Delete dengan Validasi Konfirmasi String Ketat Sisi Server)
    if ($method === 'DELETE') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            $data = $_POST;
        }

        $semesterId  = filter_var($data['semesterId'] ?? null, FILTER_VALIDATE_INT);
        $confirmText = trim((string)($data['confirmationText'] ?? $data['confirmText'] ?? ''));

        if ($semesterId === false || $semesterId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Semester ID tidak valid.']);
            exit;
        }

        // Validasi String Konfirmasi Hapus Persis Sama di Sisi Server
        $expectedPhrase = "Saya " . $userName . " mengerti bahwa dengan menghapus semester maka segala mata kuliah serta tugas di dalam semester ini akan ikut terhapus.";

        if ($confirmText === '' || $confirmText !== $expectedPhrase) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Kalimat konfirmasi penghapusan tidak sesuai. Penghapusan dibatalkan.'
            ]);
            exit;
        }

        // Cascade Deletion Protocol: Kumpulkan semua child drive_folder_id dari tugas, materi, dan jawaban di bawah semester ini
        $driveFolderIds = [];

        // 1. Dari tabel tasks
        $stmtTaskFolders = $pdo->prepare("SELECT drive_folder_id FROM tasks WHERE semesterId = :id AND drive_folder_id IS NOT NULL");
        $stmtTaskFolders->execute(['id' => $semesterId]);
        foreach ($stmtTaskFolders->fetchAll(PDO::FETCH_COLUMN) as $fId) {
            if (!empty($fId)) {
                $driveFolderIds[] = $fId;
            }
        }

        // 2. Dari tabel learning_material
        $stmtMatFolders = $pdo->prepare("
            SELECT lm.drive_folder_id 
            FROM learning_material lm 
            JOIN courses c ON lm.courseId = c.courseId 
            WHERE c.semesterId = :id AND lm.drive_folder_id IS NOT NULL
        ");
        $stmtMatFolders->execute(['id' => $semesterId]);
        foreach ($stmtMatFolders->fetchAll(PDO::FETCH_COLUMN) as $fId) {
            if (!empty($fId)) {
                $driveFolderIds[] = $fId;
            }
        }

        // 3. Dari tabel task_shared_answers
        $stmtAnsFolders = $pdo->prepare("
            SELECT tsa.drive_folder_id 
            FROM task_shared_answers tsa 
            JOIN tasks t ON tsa.taskId = t.taskId 
            WHERE t.semesterId = :id AND tsa.drive_folder_id IS NOT NULL
        ");
        $stmtAnsFolders->execute(['id' => $semesterId]);
        foreach ($stmtAnsFolders->fetchAll(PDO::FETCH_COLUMN) as $fId) {
            if (!empty($fId)) {
                $driveFolderIds[] = $fId;
            }
        }

        // Hapus permanen seluruh folder di Google Drive sebelum eksekusi database
        if (!empty($driveFolderIds)) {
            DriveManager::getInstance()->deleteFolders($driveFolderIds);
        }

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("
            UPDATE semesters
            SET deletionStatus = 1, isActive = 0, deletedByUserId = :userId, updatedAt = NOW()
            WHERE semesterId = :id AND deletionStatus = 0 AND majorType = :m AND studyProgram = :p AND classGroup = :c AND batchYear = :b
        ");
        $stmtDelete->execute([
            'userId' => $userId,
            'id'     => $semesterId,
            'm'      => $majorType,
            'p'      => $studyProgram,
            'c'      => $classGroup,
            'b'      => $batchYear
        ]);

        if ($stmtDelete->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Semester tidak ditemukan atau Anda tidak memiliki akses.']);
            exit;
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Semester berhasil dihapus!']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method HTTP tidak didukung.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}