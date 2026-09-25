<?php
/**
 * ====================================================================================
 * MODULE: Backend API Courses (Course Detail, Task List & Material List Management)
 * FILE LOCATION: app/api/courses.php
 * ====================================================================================
 *
 * TUJUAN (PURPOSE):
 * File ini berfungsi sebagai API Endpoint terpusat untuk mengambil informasi detail mata kuliah,
 * serta menyediakan data daftar tugas (Tasks) dan daftar materi pembelajaran (Materials)
 * berdasarkan courseId. Dilengkapi dengan fitur pencarian (search), pengurutan (sorting),
 * pemuatan bertahap (lazy loading), serta penanganan item yang di-soft-delete (ditaruh di bawah
 * dengan status disabled dan teks informasi penghapusan).
 *
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. courses (Main Table):
 *    - PK: courseId
 *    - FK: semesterId -> semesters.semesterId (ON DELETE CASCADE)
 * 2. tasks & learning_material (Data Source Tables):
 *    - Mengambil data tugas dan materi yang berelasi dengan courseId.
 *    - Membaca kolom soft-delete: `deletionStatus`, `deletedByUserId`, `deletedAt`.
 * 3. users (Relasi Editor/Penghapus):
 *    - Mengambil nama pengguna (`userName`) dari `deletedByUserId` untuk ditampilkan pada item yang dihapus.
 *
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/course_detail.js
 * - Views: app/views/course_detail.php
 * - Helper / DB: app/api/db.php, app/config/config.php, app/api/helpers.php
 *
 * CARA KERJA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi: Memeriksa session aktif $_SESSION['user_id']. Jika tidak, return HTTP 401 / JSON.
 * 2. Identifikasi Request (Action / Method Routing):
 *    - GET:
 *      a) action = 'detail': Mengambil informasi lengkap mata kuliah dan semester aktifnya.
 *      b) action = 'list_tasks': Mengambil daftar tugas untuk tab Tugas dengan ketentuan:
 *         - Mendukung pagination / lazy loading (default limit 10 item per *scroll*).
 *         - Mendukung pencarian (search) berdasarkan judul atau deskripsi.
 *         - Mendukung pengurutan (sorting): Judul (ASC/DESC) atau Deadline (ASC/DESC).
 *         - ATURAN SORTING & POSISI DELETE: Item yang aktif (`deletionStatus = 0`) diurutkan sesuai pilihan user di atas,
 *           sedangkan item yang sudah di-soft-delete (`deletionStatus = 1`) selalu dipaksa ditaruh di urutan paling bawah,
 *           dilengkapi data siapa yang menghapus (`userName` dari `deletedByUserId`) dan kapan (`deletedAt`).
 *      c) action = 'list_materials': Mengambil daftar materi untuk tab Materi dengan ketentuan:
 *         - Mendukung pagination / lazy loading (default limit 10 item).
 *         - Mendukung pencarian (search) berdasarkan judul atau deskripsi materi.
 *         - Mendukung pengurutan (sorting): Judul (ASC/DESC) atau Tanggal Dibuat (ASC/DESC).
 *         - ATURAN SORTING & POSISI DELETE: Item aktif di atas, item `deletionStatus = 1` ditaruh di urutan paling bawah,
 *           lengkap dengan informasi `Dihapus oleh [userName] pada [timestamp]`.
 *
 * ATURAN BISNIS & SECURITY (BUSINESS RULES):
 * - Kartu yang sudah di-soft-delete (`deletionStatus = 1`) bersifat *disabled* (tidak bisa diklik ke halaman detail).
 * - Gunakan PDO Prepared Statements pada semua kueri SQL.
 * - Format output JSON konsisten: ['success' => bool, 'message' => string, 'data' => mixed].
 */

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

$userId = (int)$_SESSION['user_id'];
$method =$_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    $stmtUser =$pdo->prepare("SELECT roleLevel FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' =>$userId]);
    $user =$stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $roleLevel = strtolower($user['roleLevel'] ?? 'keroco');
    $isManager = in_array($roleLevel, ['primordial', 'sepuh']);

    // ==========================================
    // 1. GET REQUESTS (Routing via parameter 'action')
    // ==========================================
    if ($method === 'GET') {
        $action =$_GET['action'] ?? '';

        // --- A. GET DETAIL MATA KULIAH ---
        if ($action === 'detail') {
            $courseId = (int)($_GET['courseId'] ?? 0);
            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid']);
                exit;
            }
            $stmt =$pdo->prepare("SELECT * FROM courses WHERE courseId = :courseId");
            $stmt->execute(['courseId' =>$courseId]);
            $course =$stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $course]);
            exit;
        }

        // --- B. GET LIST TUGAS (LAZY LOADING, SEARCH, SORT, SOFT-DELETE) ---
        elseif ($action === 'list_tasks') {$courseId = (int)($_GET['courseId'] ?? 0);$page     = max(1, (int)($_GET['page'] ?? 1));$limit    = min(100, max(1, (int)($_GET['limit'] ?? 10)));$offset   = ($page - 1) *$limit;
            $search   = trim($_GET['search'] ?? '');
            $sort     = trim($_GET['sort'] ?? 'due_asc');

            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid']);
                exit;
            }

            // Sorting logic (hanya berlaku untuk item yang tidak didelete)
            $orderSql = "t.dueDate ASC";
            if ($sort === 'due_desc')$orderSql = "t.dueDate DESC";
            if ($sort === 'title_asc')$orderSql = "t.taskTitle ASC";
            if ($sort === 'title_desc')$orderSql = "t.taskTitle DESC";

            // Fitur search
            $searchSql = "";
            $params = [':courseId' =>$courseId];
            if ($search !== '') {$searchSql = " AND (t.taskTitle LIKE :searchTitle OR t.taskDescription LIKE :searchDescription)";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            // Hitung total data untuk lazy loading
            $stmtCount =$pdo->prepare("SELECT COUNT(taskId) FROM tasks t WHERE t.courseId = :courseId $searchSql");
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
            foreach ($params as$key => $val) {$stmtTasks->bindValue($key,$val);
            }
            $stmtTasks->bindValue(':userId', $userId, PDO::PARAM_INT);$stmtTasks->bindValue(':limit', $limit, PDO::PARAM_INT);$stmtTasks->bindValue(':offset', $offset, PDO::PARAM_INT);$stmtTasks->execute();
            $tasks =$stmtTasks->fetchAll(PDO::FETCH_ASSOC);

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
                    if ($t['isCompleted']) {$t['statusBadge'] = 'Selesai';
                    } elseif ($dueDate < $now) {$t['statusBadge'] = 'Telat';
                    } else {$t['statusBadge'] = 'Tersedia';}
                    $t['isClickable'] = true;
                }

                $t['taskDescription'] = strlen((string)$t['taskDescription']) > 100
                                      ? substr($t['taskDescription'], 0, 100) . '...'                                        :$t['taskDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'tasks'   => $tasks,
                    'total'   => $totalTasks,
                    'page'    => $page,
                    'limit'   => $limit,
                    'hasMore' => ($offset + $limit) <$totalTasks
                ]
            ]);
            exit;
        }

        // --- C. GET LIST MATERI (LAZY LOADING, SEARCH, SORT, SOFT-DELETE) ---
        elseif ($action === 'list_materials') {$courseId = (int)($_GET['courseId'] ?? 0);$page     = max(1, (int)($_GET['page'] ?? 1));$limit    = min(100, max(1, (int)($_GET['limit'] ?? 10)));$offset   = ($page - 1) *$limit;
            $search   = trim($_GET['search'] ?? '');
            $sort     = trim($_GET['sort'] ?? 'title_asc');

            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid']);
                exit;
            }

            // Sorting logic
            $orderSql = "m.materialTitle ASC";
            if ($sort === 'title_desc')$orderSql = "m.materialTitle DESC";
            if ($sort === 'date_asc')$orderSql = "m.createdAt ASC";
            if ($sort === 'date_desc')$orderSql = "m.createdAt DESC";

            // Fitur search
            $searchSql = "";
            $params = [':courseId' =>$courseId];
            if ($search !== '') {$searchSql = " AND (m.materialTitle LIKE :searchTitle OR m.materialDescription LIKE :searchDescription)";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            // Hitung total data
            $stmtCount =$pdo->prepare("SELECT COUNT(materialId) FROM learning_material m WHERE m.courseId = :courseId $searchSql");
            $stmtCount->execute($params);
            $totalMaterials = (int)$stmtCount->fetchColumn();

            // Query utama: deletionStatus = 0 di atas, 1 di bawah
            $sql = "
                SELECT m.materialId, m.materialTitle, m.materialDescription, m.createdAt,
                       m.deletionStatus, m.deletedAt, u.userName AS deletedByUserName
                FROM learning_material m
                LEFT JOIN users u ON m.deletedByUserId = u.userId
                WHERE m.courseId = :courseId $searchSql
                ORDER BY m.deletionStatus ASC, $orderSql
                LIMIT :limit OFFSET :offset
            ";

            $stmtMat = $pdo->prepare($sql);
            foreach ($params as$key => $val) {$stmtMat->bindValue($key,$val);
            }
            $stmtMat->bindValue(':limit', $limit, PDO::PARAM_INT);$stmtMat->bindValue(':offset', $offset, PDO::PARAM_INT);$stmtMat->execute();
            $materials =$stmtMat->fetchAll(PDO::FETCH_ASSOC);

            // Potong deskripsi panjang
            foreach ($materials as &$m) {
                $m['isDeleted'] = (bool)$m['deletionStatus'];
                $m['isClickable'] = !$m['isDeleted'];
                if ($m['isDeleted']) {
                    $m['statusBadge'] = 'Dihapus';
                    $m['deletedMessage'] = 'Dihapus oleh ' . ($m['deletedByUserName'] ?: 'Pengguna') . ' pada ' . $m['deletedAt'];
                }
                $m['materialDescription'] = strlen((string)$m['materialDescription']) > 100
                                          ? substr($m['materialDescription'], 0, 100) . '...'                                            :$m['materialDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'materials' => $materials,
                    'total'     => $totalMaterials,
                    'page'      => $page,
                    'limit'     => $limit,
                    'hasMore'   => ($offset + $limit) <$totalMaterials
                ]
            ]);
            exit;
        }

        // --- D. GET DEFAULT (List Matkul di halaman Semester) ---
        else {
            $semesterId = (int)($_GET['semesterId'] ?? 0);

            $stmt =$pdo->prepare("
                SELECT c.*
                FROM courses c
                JOIN semesters s ON c.semesterId = s.semesterId
                WHERE c.semesterId = :semId AND s.deletionStatus = 0
                ORDER BY c.courseCode ASC
            ");
            $stmt->execute(['semId' =>$semesterId]);

            $courses =$stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'   => true,
                'canManage' => $isManager,
                'data'      => $courses
            ]);
            exit;
        }
    }

    // ==========================================
    // BLOCK Otorisasi khusus Modifikasi Matkul (Khusus Manager/Sepuh/Primordial)
    // ==========================================
    if (!$isManager) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Khusus Primordial dan Sepuh.']);
        exit;
    }

    $inputData = [];
    if ($method === 'PUT' || $method === 'DELETE') {
        if (!empty($_POST)) {
            $inputData = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            $inputData = is_array($json) ? $json : [];
        }
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
        $courseClass       = trim($_POST['courseClass'] ?? '');
        $courseDay         = trim($_POST['courseDay'] ?? '');
        $startTime         = trim($_POST['startTime'] ?? '');
        $endTime           = trim($_POST['endTime'] ?? '');

        if ($semesterId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Kode dan Judul Matkul wajib diisi.']);
            exit;
        }

        if (!in_array($courseType, ['Teori', 'Praktek'])) {$courseType = 'Teori';
        }

        $stmtSem =$pdo->prepare("SELECT semesterNumber, majorType, studyProgram, classGroup, batchYear FROM semesters WHERE semesterId = :semId LIMIT 1");
        $stmtSem->execute(['semId' =>$semesterId]);
        $sem =$stmtSem->fetch(PDO::FETCH_ASSOC);

        if (!$sem) {
            echo json_encode(['success' => false, 'message' => 'Data semester tidak ditemukan.']);
            exit;
        }

        $stmtInsert =$pdo->prepare("
            INSERT INTO courses (
                semesterId, semesterNumber, courseCode, courseTitle, courseType,
                courseClass, courseDay, startTime, endTime,
                courseDescription, lecturerName, lecturerEmail, lecturerPhone,
                backgroundColor, majorType, studyProgram, classGroup, batchYear
            ) VALUES (
                :semId, :semNum, :code, :title, :type,
                :class, :day, :start, :end,
                :desc, :lname, :lemail, :lphone,
                :bg, :major, :prodi, :kelas, :batch
            )
        ");

        $stmtInsert->execute([
            'semId'   => $semesterId, 'semNum' =>$sem['semesterNumber'],
            'code'    => $courseCode, 'title'  => $courseTitle, 'type' =>$courseType,
            'class'   => $courseClass, 'day'   =>$courseDay, 'start'  => $startTime, 'end' =>$endTime,
            'desc'    => $courseDescription, 'lname' =>$lecturerName,
            'lemail'  => $lecturerEmail, 'lphone' => $lecturerPhone, 'bg' =>$backgroundColor,
            'major'   => $sem['majorType'], 'prodi' =>$sem['studyProgram'],
            'kelas'   => $sem['classGroup'], 'batch' =>$sem['batchYear']
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
        $courseClass       = trim($inputData['courseClass'] ?? '');
        $courseDay         = trim($inputData['courseDay'] ?? '');
        $startTime         = trim($inputData['startTime'] ?? '');
        $endTime           = trim($inputData['endTime'] ?? '');

        if ($courseId <= 0 || empty($courseCode) || empty($courseTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data edit tidak valid.']);
            exit;
        }

        if (!in_array($courseType, ['Teori', 'Praktek'])) {$courseType = 'Teori';
        }

        $stmtUpdate =$pdo->prepare("
            UPDATE courses
            SET courseCode = :code, courseTitle = :title, courseType = :type,
                courseClass = :class, courseDay = :day, startTime = :start, endTime = :end,
                courseDescription = :desc, lecturerName = :lname,
                lecturerEmail = :lemail, lecturerPhone = :lphone, backgroundColor = :bg
            WHERE courseId = :id
        ");
        $stmtUpdate->execute([
            'code'  => $courseCode, 'title' => $courseTitle, 'type' =>$courseType,
            'class' => $courseClass, 'day'  =>$courseDay, 'start' => $startTime, 'end' =>$endTime,
            'desc'  => $courseDescription, 'lname' =>$lecturerName,
            'lemail'=> $lecturerEmail, 'lphone'=>$lecturerPhone, 'bg' => $backgroundColor, 'id' =>$courseId
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

        $stmtDelTasks =$pdo->prepare("DELETE FROM tasks WHERE courseId = :id");
        $stmtDelTasks->execute(['id' =>$courseId]);

        $stmtDelete =$pdo->prepare("DELETE FROM courses WHERE courseId = :id");
        $stmtDelete->execute(['id' =>$courseId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Mata kuliah dan seluruh tugasnya berhasil dihapus!']);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {$pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}