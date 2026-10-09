<?php
/**
 * ====================================================================================
 * MODULE: Backend API Courses (Course Detail, Task List, Material List & Course Management)
 * FILE LOCATION: app/api/courses.php
 * ====================================================================================
 */

require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    // 1. Ambil Profil & Role User dari Database
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

    $roleLevel    = strtolower($user['roleLevel'] ?? 'keroco');
    $isManager    = in_array($roleLevel, ['primordial', 'sepuh'], true);
    $userName     = trim($user['userName'] ?? '');
    $majorType    = $user['majorType'] ?? '';
    $studyProgram = $user['studyProgram'] ?? '';
    $classGroup   = $user['classGroup'] ?? '';
    $batchYear    = $user['batchYear'] ?? '';

    // ==========================================
    // 1. GET REQUESTS (Routing via parameter 'action')
    // ==========================================
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        // --- A. GET DETAIL MATA KULIAH ---
        if ($action === 'detail') {
            $courseId = (int)($_GET['courseId'] ?? 0);
            if ($courseId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT c.* 
                FROM courses c
                JOIN semesters s ON c.semesterId = s.semesterId
                WHERE c.courseId = :courseId AND s.deletionStatus = 0
                LIMIT 1
            ");
            $stmt->execute(['courseId' => $courseId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $course]);
            exit;
        }

        // --- B. GET LIST TUGAS (LAZY LOADING, SEARCH, SORT, SOFT-DELETE) ---
        elseif ($action === 'list_tasks') {
            $courseId = (int)($_GET['courseId'] ?? 0);
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 10)));
            $offset   = ($page - 1) * $limit;
            $search   = trim(strip_tags((string)($_GET['search'] ?? '')));
            $sort     = trim((string)($_GET['sort'] ?? 'due_asc'));

            if ($courseId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid.']);
                exit;
            }

            // Sorting logic
            $orderSql = "t.dueDate ASC";
            if ($sort === 'due_desc') $orderSql = "t.dueDate DESC";
            if ($sort === 'title_asc') $orderSql = "t.taskTitle ASC";
            if ($sort === 'title_desc') $orderSql = "t.taskTitle DESC";

            // Fitur search
            $searchSql = "";
            $params = [':courseId' => $courseId];
            if ($search !== '') {
                $searchSql = " AND (t.taskTitle LIKE :searchTitle OR t.taskDescription LIKE :searchDescription)";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            // Hitung total data untuk lazy loading
            $stmtCount = $pdo->prepare("SELECT COUNT(taskId) FROM tasks t WHERE t.courseId = :courseId $searchSql");
            $stmtCount->execute($params);
            $totalTasks = (int)$stmtCount->fetchColumn();

            // Query utama: Urutkan berdasarkan deletionStatus ASC (0 dulu baru 1), lalu order pilihan user
            $sql = "
                SELECT t.taskId, t.taskTitle, t.taskDescription, t.dueDate, t.taskType,
                       t.deletionStatus, t.deletedAt, u.userName AS deletedByUserName,
                       COALESCE(tc.completionId, 0) AS isCompleted
                FROM tasks t
                LEFT JOIN users u ON t.deletedByUserId = u.userId
                LEFT JOIN task_completions tc ON t.taskId = tc.taskId AND tc.userId = :userId
                WHERE t.courseId = :courseId $searchSql
                ORDER BY t.deletionStatus ASC, $orderSql
                LIMIT :limit OFFSET :offset
            ";

            $stmtTasks = $pdo->prepare($sql);
            foreach ($params as $key => $val) {
                $stmtTasks->bindValue($key, $val);
            }
            $stmtTasks->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmtTasks->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtTasks->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtTasks->execute();
            $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

            // Set badge status dan potong deskripsi panjang
            $now = new DateTime();
            foreach ($tasks as &$t) {
                $t['isDeleted'] = (bool)$t['deletionStatus'];
                if ($t['isDeleted']) {
                    $t['statusBadge'] = 'Dihapus';
                    $t['deletedMessage'] = 'Dihapus oleh ' . ($t['deletedByUserName'] ?: 'Pengguna') . ' pada ' . $t['deletedAt'];
                    $t['isClickable'] = false;
                } else {
                    $dueDate = new DateTime($t['dueDate']);
                    if ($t['isCompleted']) {
                        $t['statusBadge'] = 'Selesai';
                    } elseif ($dueDate < $now) {
                        $t['statusBadge'] = 'Telat';
                    } else {
                        $t['statusBadge'] = 'Tersedia';
                    }
                    $t['isClickable'] = true;
                }

                $t['taskDescription'] = strlen((string)$t['taskDescription']) > 100
                    ? substr($t['taskDescription'], 0, 100) . '...'
                    : $t['taskDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'tasks'   => $tasks,
                    'total'   => $totalTasks,
                    'page'    => $page,
                    'limit'   => $limit,
                    'hasMore' => ($offset + $limit) < $totalTasks,
                    'canHardDelete' => $isManager
                ]
            ]);
            exit;
        }

        // --- C. GET LIST MATERI (LAZY LOADING, SEARCH, SORT, SOFT-DELETE) ---
        elseif ($action === 'list_materials') {
            $courseId = (int)($_GET['courseId'] ?? 0);
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 10)));
            $offset   = ($page - 1) * $limit;
            $search   = trim(strip_tags((string)($_GET['search'] ?? '')));
            $sort     = trim((string)($_GET['sort'] ?? 'title_asc'));

            if ($courseId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid.']);
                exit;
            }

            // Sorting logic
            $orderSql = "m.materialTitle ASC";
            if ($sort === 'title_desc') $orderSql = "m.materialTitle DESC";
            if ($sort === 'date_asc') $orderSql = "m.createdAt ASC";
            if ($sort === 'date_desc') $orderSql = "m.createdAt DESC";

            // Fitur search
            $searchSql = "";
            $params = [':courseId' => $courseId];
            if ($search !== '') {
                $searchSql = " AND (m.materialTitle LIKE :searchTitle OR m.materialDescription LIKE :searchDescription)";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            // Hitung total data
            $stmtCount = $pdo->prepare("SELECT COUNT(materialId) FROM learning_material m WHERE m.courseId = :courseId $searchSql");
            $stmtCount->execute($params);
            $totalMaterials = (int)$stmtCount->fetchColumn();

            // Query utama: deletionStatus = 0 di atas, 1 di bawah
            $sql = "
                SELECT m.materialId, m.materialTitle, m.materialDescription, m.createdAt,
                       m.deletionStatus, m.deletedAt, u.userName AS deletedByUserName,
                       au.userName AS authorName
                FROM learning_material m
                LEFT JOIN users u ON m.deletedByUserId = u.userId
                LEFT JOIN users au ON m.uploadedByUserId = au.userId
                WHERE m.courseId = :courseId $searchSql
                ORDER BY m.deletionStatus ASC, $orderSql
                LIMIT :limit OFFSET :offset
            ";

            $stmtMat = $pdo->prepare($sql);
            foreach ($params as $key => $val) {
                $stmtMat->bindValue($key, $val);
            }
            $stmtMat->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtMat->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtMat->execute();
            $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

            // Potong deskripsi panjang
            foreach ($materials as &$m) {
                $m['isDeleted'] = (bool)$m['deletionStatus'];
                $m['isClickable'] = !$m['isDeleted'];
                if ($m['isDeleted']) {
                    $m['statusBadge'] = 'Dihapus';
                    $m['deletedMessage'] = 'Dihapus oleh ' . ($m['deletedByUserName'] ?: 'Pengguna') . ' pada ' . $m['deletedAt'];
                }
                $m['materialDescription'] = strlen((string)$m['materialDescription']) > 100
                    ? substr($m['materialDescription'], 0, 100) . '...'
                    : $m['materialDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'materials' => $materials,
                    'total'     => $totalMaterials,
                    'page'      => $page,
                    'limit'     => $limit,
                    'hasMore'   => ($offset + $limit) < $totalMaterials
                ]
            ]);
            exit;
        }

        // --- D. GET DEFAULT (List Matkul di halaman Semester) ---
        else {
            $semesterId = (int)($_GET['semesterId'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT c.*
                FROM courses c
                JOIN semesters s ON c.semesterId = s.semesterId
                WHERE c.semesterId = :semId AND s.deletionStatus = 0
                ORDER BY c.courseTitle ASC, c.courseId ASC
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
    }

    // =========================================================================
    // Otorisasi Pengelolaan Sisi Server (RBAC Enforcement)
    // HANYA Primordial dan Sepuh yang dapat melakukan POST, PUT, atau DELETE
    // =========================================================================
    if (!$isManager) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Sepuh dan Primordial yang memiliki izin mengelola mata kuliah.']);
        exit;
    }

    $inputData = [];
    if ($method === 'PUT' || $method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $inputData = is_array($json) ? $json : $_POST;
    }

    // =========================================================================
    // 2. POST: Tambah Mata Kuliah (Audit & Sanitasi Input Ketat)
    // =========================================================================
    if ($method === 'POST') {
        $semesterId        = filter_var($_POST['semesterId'] ?? null, FILTER_VALIDATE_INT);
        $courseCode        = trim(strip_tags((string)($_POST['courseCode'] ?? '')));
        $courseTitle       = trim(strip_tags((string)($_POST['courseTitle'] ?? '')));
        $courseType        = trim((string)($_POST['courseType'] ?? 'Teori'));
        $courseDescription = trim(strip_tags((string)($_POST['courseDescription'] ?? '')));
        $lecturerName      = trim(strip_tags((string)($_POST['lecturerName'] ?? '')));
        $lecturerEmail     = trim((string)($_POST['lecturerEmail'] ?? ''));
        $lecturerPhone     = trim((string)($_POST['lecturerPhone'] ?? ''));
        $backgroundColor   = trim((string)($_POST['backgroundColor'] ?? '#10b981'));
        $courseClass       = trim(strip_tags((string)($_POST['courseClass'] ?? '')));
        $courseDay         = trim((string)($_POST['courseDay'] ?? ''));
        $startTime         = trim((string)($_POST['startTime'] ?? ''));
        $endTime           = trim((string)($_POST['endTime'] ?? ''));

        if ($semesterId === false || $semesterId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Semester ID tidak valid.']);
            exit;
        }

        if (mb_strlen($courseCode) < 1 || mb_strlen($courseCode) > 50) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Kode mata kuliah wajib diisi (maksimal 50 karakter).']);
            exit;
        }

        if (mb_strlen($courseTitle) < 1 || mb_strlen($courseTitle) > 150) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Judul mata kuliah wajib diisi (maksimal 150 karakter).']);
            exit;
        }

        $courseType = ($courseType === 'Praktek') ? 'Praktek' : 'Teori';

        if (mb_strlen($courseDescription) > 1000) {
            $courseDescription = mb_substr($courseDescription, 0, 1000);
        }

        if (mb_strlen($lecturerName) > 150) {
            $lecturerName = mb_substr($lecturerName, 0, 150);
        }

        if ($lecturerEmail !== '' && !filter_var($lecturerEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Format email dosen tidak valid.']);
            exit;
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor)) {
            $backgroundColor = '#10b981';
        }

        $validDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        if (!in_array($courseDay, $validDays, true)) {
            $courseDay = null;
        }

        if ($startTime !== '' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $startTime)) {
            $startTime = null;
        }
        if ($endTime !== '' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $endTime)) {
            $endTime = null;
        }

        // Verifikasi kepemilikan semester aktif di kelompok akademik user
        $stmtSem = $pdo->prepare("
            SELECT semesterNumber, majorType, studyProgram, classGroup, batchYear 
            FROM semesters 
            WHERE semesterId = :semId 
              AND deletionStatus = 0 
              AND majorType = :m 
              AND studyProgram = :p 
              AND classGroup = :c 
              AND batchYear = :b 
            LIMIT 1
        ");
        $stmtSem->execute([
            'semId' => $semesterId,
            'm'     => $majorType,
            'p'     => $studyProgram,
            'c'     => $classGroup,
            'b'     => $batchYear
        ]);
        $sem = $stmtSem->fetch(PDO::FETCH_ASSOC);

        if (!$sem) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data semester tidak ditemukan atau Anda tidak memiliki akses.']);
            exit;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO courses (
                semesterId, semesterNumber, courseCode, courseTitle, courseType,
                courseClass, courseDay, startTime, endTime,
                courseDescription, lecturerName, lecturerEmail, lecturerPhone,
                backgroundColor, majorType, studyProgram, classGroup, batchYear,
                createdAt, updatedAt
            ) VALUES (
                :semId, :semNum, :code, :title, :type,
                :class, :day, :start, :end,
                :desc, :lname, :lemail, :lphone,
                :bg, :major, :prodi, :kelas, :batch,
                NOW(), NOW()
            )
        ");

        $stmtInsert->execute([
            'semId'   => $semesterId,
            'semNum'  => $sem['semesterNumber'],
            'code'    => $courseCode,
            'title'   => $courseTitle,
            'type'    => $courseType,
            'class'   => $courseClass,
            'day'     => $courseDay,
            'start'   => $startTime,
            'end'     => $endTime,
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

    // =========================================================================
    // 3. PUT: Edit Mata Kuliah (Audit & Validasi Input)
    // =========================================================================
    if ($method === 'PUT') {
        $courseId          = filter_var($inputData['courseId'] ?? null, FILTER_VALIDATE_INT);
        $courseCode        = trim(strip_tags((string)($inputData['courseCode'] ?? '')));
        $courseTitle       = trim(strip_tags((string)($inputData['courseTitle'] ?? '')));
        $courseType        = trim((string)($inputData['courseType'] ?? 'Teori'));
        $courseDescription = trim(strip_tags((string)($inputData['courseDescription'] ?? '')));
        $lecturerName      = trim(strip_tags((string)($inputData['lecturerName'] ?? '')));
        $lecturerEmail     = trim((string)($inputData['lecturerEmail'] ?? ''));
        $lecturerPhone     = trim((string)($inputData['lecturerPhone'] ?? ''));
        $backgroundColor   = trim((string)($inputData['backgroundColor'] ?? '#10b981'));
        $courseClass       = trim(strip_tags((string)($inputData['courseClass'] ?? '')));
        $courseDay         = trim((string)($inputData['courseDay'] ?? ''));
        $startTime         = trim((string)($inputData['startTime'] ?? ''));
        $endTime           = trim((string)($inputData['endTime'] ?? ''));

        if ($courseId === false || $courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID tidak valid.']);
            exit;
        }

        if (mb_strlen($courseCode) < 1 || mb_strlen($courseCode) > 50) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Kode mata kuliah wajib diisi (maksimal 50 karakter).']);
            exit;
        }

        if (mb_strlen($courseTitle) < 1 || mb_strlen($courseTitle) > 150) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Judul mata kuliah wajib diisi (maksimal 150 karakter).']);
            exit;
        }

        $courseType = ($courseType === 'Praktek') ? 'Praktek' : 'Teori';

        if (mb_strlen($courseDescription) > 1000) {
            $courseDescription = mb_substr($courseDescription, 0, 1000);
        }

        if (mb_strlen($lecturerName) > 150) {
            $lecturerName = mb_substr($lecturerName, 0, 150);
        }

        if ($lecturerEmail !== '' && !filter_var($lecturerEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Format email dosen tidak valid.']);
            exit;
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $backgroundColor)) {
            $backgroundColor = '#10b981';
        }

        $validDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        if (!in_array($courseDay, $validDays, true)) {
            $courseDay = null;
        }

        if ($startTime !== '' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $startTime)) {
            $startTime = null;
        }
        if ($endTime !== '' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $endTime)) {
            $endTime = null;
        }

        // Pastikan mata kuliah ada dan berada di bawah grup akademik user
        $stmtCheck = $pdo->prepare("
            SELECT courseId 
            FROM courses 
            WHERE courseId = :id 
              AND majorType = :m 
              AND studyProgram = :p 
              AND classGroup = :c 
              AND batchYear = :b 
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $courseId,
            'm'  => $majorType,
            'p'  => $studyProgram,
            'c'  => $classGroup,
            'b'  => $batchYear
        ]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan atau Anda tidak memiliki akses.']);
            exit;
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE courses
            SET courseCode = :code, courseTitle = :title, courseType = :type,
                courseClass = :class, courseDay = :day, startTime = :start, endTime = :end,
                courseDescription = :desc, lecturerName = :lname,
                lecturerEmail = :lemail, lecturerPhone = :lphone, backgroundColor = :bg,
                updatedAt = NOW()
            WHERE courseId = :id
        ");
        $stmtUpdate->execute([
            'code'   => $courseCode,
            'title'  => $courseTitle,
            'type'   => $courseType,
            'class'  => $courseClass,
            'day'    => $courseDay,
            'start'  => $startTime,
            'end'    => $endTime,
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

    // =========================================================================
    // 4. DELETE: Hapus Mata Kuliah (Validasi Konfirmasi String Ketat Sisi Server)
    // =========================================================================
    if ($method === 'DELETE') {
        $courseId    = filter_var($inputData['courseId'] ?? null, FILTER_VALIDATE_INT);
        $confirmText = trim((string)($inputData['confirmationText'] ?? $inputData['confirmText'] ?? ''));

        if ($courseId === false || $courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID Mata Kuliah tidak valid.']);
            exit;
        }

        // Validasi String Konfirmasi Hapus Persis Sama di Sisi Server
        $expectedPhrase = "Saya " . $userName . " mengerti bahwa dengan menghapus mata kuliah ini maka seluruh tugas di dalamnya akan ikut terhapus.";

        if ($confirmText === '' || $confirmText !== $expectedPhrase) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Kalimat konfirmasi penghapusan tidak sesuai. Penghapusan dibatalkan.'
            ]);
            exit;
        }

        // Pastikan mata kuliah ada dan berada di bawah grup akademik user
        $stmtCheck = $pdo->prepare("
            SELECT courseId 
            FROM courses 
            WHERE courseId = :id 
              AND majorType = :m 
              AND studyProgram = :p 
              AND classGroup = :c 
              AND batchYear = :b 
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $courseId,
            'm'  => $majorType,
            'p'  => $studyProgram,
            'c'  => $classGroup,
            'b'  => $batchYear
        ]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan atau Anda tidak memiliki akses.']);
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

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method HTTP tidak didukung.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}