<?php
/**
 * ====================================================================================
 * MODULE: Backend API Tasks (Sharing Center - Task Management)
 * FILE LOCATION: app/api/tasks.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini berfungsi sebagai API Endpoint terpusat untuk menangani seluruh operasi CRUD
 * data tugas, pengunggahan multi-file lampiran, penandaan tugas selesai, komentar publik,
 * pencatatan histori pengeditan, serta penghapusan data secara permanen (Hard Delete).
 * 
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. tasks (Main Table):
 *    - PK: taskId
 *    - FK: courseId -> courses.courseId (ON DELETE CASCADE)
 *    - FK: semesterId -> semesters.semesterId (ON DELETE CASCADE)
 *    - FK: createdByUserId -> users.userId (ON DELETE SET NULL)
 *    - FK: lastEditedByUserId -> users.userId (ON DELETE SET NULL)
 * 2. task_files (Child Table Multi-File):
 *    - PK: fileId
 *    - FK: taskId -> tasks.taskId (ON DELETE CASCADE)
 * 3. task_completions (Child Table Status Selesai):
 *    - PK: completionId
 *    - FK: taskId -> tasks.taskId (ON DELETE CASCADE)
 *    - FK: userId -> users.userId (ON DELETE CASCADE)
 * 4. task_comments (Child Table Komentar):
 *    - PK: commentId
 *    - FK: taskId -> tasks.taskId (ON DELETE CASCADE)
 *    - FK: userId -> users.userId (ON DELETE CASCADE)
 * 5. task_shared_answers (Child Table Sharing Jawaban):
 *    - FK: taskId -> tasks.taskId (ON DELETE CASCADE)
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/task_detail.js, public/js/modules/course_detail.js
 * - Views: app/views/task_detail.php, app/views/course_detail.php
 * - Helper / DB: app/api/db.php, app/config/config.php, app/api/helpers.php
 * 
 * CARA KERJA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi: Memeriksa session aktif $_SESSION['user_id']. Jika tidak ada, return HTTP 401 / JSON success: false.
 * 2. Identifikasi Request (Action / Method Routing):
 *    - GET:
 *      a) action = 'detail': Mengambil detail 1 tugas berdasarkan taskId (termasuk data pembuat, editor terakhir, daftar file dari task_files, status apakah user login sudah tandai selesai, daftar user yang sudah selesai, dan komentar).
 *      b) action = 'list': Mengambil daftar tugas berdasarkan courseId dengan dukungan pagination/lazy loading, search, dan sorting (due_asc, due_desc, title_asc, title_desc).
 * 
 *    - POST (Create Task):
 *      a) Menerima input: courseId, semesterId, taskType, taskTitle, taskDescription, dueDate, dan $_FILES['attachments'] (multi-file).
 *      b) Melakukan validasi input wajib.
 *      c) Memproses pengunggahan multi-file ke folder 'public/uploads/tasks/'.
 *      d) Memasukkan data ke tabel 'tasks' dengan createdByUserId = $_SESSION['user_id'].
 *      e) Memasukkan setiap path file terunggah ke tabel 'task_files'.
 *      f) [Aturan Poin]: Menambah +1 poin kontribusi pengguna (dapat ditangani via helper / query langsung).
 *      g) Return JSON success: true.
 * 
 *    - POST / PUT (Update Task):
 *      a) Menerima input data tugas yang diperbarui & file baru jika ada.
 *      b) Memeriksa otorisasi (Hanya pembuat tugas / role Sepuh/Primordial yang boleh edit).
 *      c) Memperbarui data tugas di tabel 'tasks'.
 *      d) Mengisi kolom 'lastEditedByUserId' = $_SESSION['user_id'] dan 'updatedAt' = NOW().
 *      e) Menambahkan file baru ke 'task_files' jika pengguna mengunggah lampiran tambahan.
 * 
 *    - DELETE / POST _method=DELETE (Hard Delete Task):
 *      a) Menerima taskId.
 *      b) OTORISASI STRICT: Memeriksa apakah $_SESSION['user_id'] === createdByUserId. Hanya author yang boleh menghapus (atau Primordial/Sepuh).
 *      c) Mengambil semua file Path terkait dari 'task_files'.
 *      d) Menghapus file fisik dari direktori server menggunakan pustaka unlink().
 *      e) Mengeksekusi SQL HARD DELETE pada tabel 'tasks'. Karena FK diset ON DELETE CASCADE, seluruh data di 'task_files', 'task_comments', 'task_completions', dan 'task_shared_answers' akan terhapus otomatis di database.
 *      f) [Catatan Poin]: Poin kontribusi author TIDAK berkurang saat delete.
 * 
 *    - POST action = 'toggle_completion':
 *      a) Menerima taskId.
 *      b) Memeriksa apakah user sudah ada di 'task_completions'.
 *      c) Jika sudah ada -> DELETE row (Batal Selesai).
 *      d) Jika belum ada -> INSERT row (Tandai Selesai) dengan completedAt = NOW().
 * 
 *    - POST action = 'add_comment':
 *      a) Menerima taskId dan commentContent.
 *      b) Simpan ke tabel 'task_comments' dengan userId = $_SESSION['user_id'].
 * 
 * ATURAN BISNIS & SECURITY (BUSINESS RULES):
 * - Jangan gunakan fitur AI Explanation (fitur telah dibatalkan).
 * - Gunakan PDO Prepared Statements pada semua kueri SQL.
 * - Tangani kegagalan upload file secara aman (rollback transaksi DB jika upload gagal).
 * - Format output HARUS JSON konsisten: ['success' => bool, 'message' => string, 'data' => mixed].
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
        


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

function saveTaskAttachments(PDO $pdo, int $taskId, array $files, string $uploadDir): void
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat');
    }

    $allowedMimeTypes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'zip'  => 'application/zip'
    ];
    $maxFileSize = 10 * 1024 * 1024;
    $uploadedPaths = [];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($fileInfo === false) {
        throw new RuntimeException('Pemeriksaan tipe file tidak tersedia');
    }

    try {
        foreach ($files['name'] as $index => $originalName) {
            $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Pengunggahan file gagal');
            }

            $tmpName = $files['tmp_name'][$index] ?? '';
            $fileSize = (int)($files['size'][$index] ?? 0);
            $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
            $mimeType = finfo_file($fileInfo, $tmpName);

            if (!is_uploaded_file($tmpName) || $fileSize <= 0 || $fileSize > $maxFileSize ||
                !isset($allowedMimeTypes[$extension]) || $mimeType !== $allowedMimeTypes[$extension]) {
                throw new RuntimeException('Tipe atau ukuran file tidak diperbolehkan');
            }

            $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $uploadDir . $safeName;
            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('File tidak dapat disimpan');
            }
            $uploadedPaths[] = $destination;

            $stmtFile = $pdo->prepare(
                'INSERT INTO task_files (taskId, filePath, fileName, fileSize, uploadedAt)
                 VALUES (:taskId, :filePath, :fileName, :fileSize, NOW())'
            );
            $stmtFile->execute([
                'taskId' => $taskId,
                'filePath' => 'public/uploads/tasks/' . $safeName,
                'fileName' => basename((string)$originalName),
                'fileSize' => $fileSize
            ]);
        }
    } catch (Throwable $exception) {
        foreach ($uploadedPaths as $uploadedPath) {
            if (is_file($uploadedPath)) {
                unlink($uploadedPath);
            }
        }
        throw $exception;
    } finally {
        finfo_close($fileInfo);
    }
}

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method =$_SERVER['REQUEST_METHOD'];

// Handle Method Override
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    // ====================================================================================
    // GET REQUESTS
    // ====================================================================================
    if ($method === 'GET') {
        // 0. GET LIST TUGAS GABUNGAN (GLOBAL)
        if ($action === 'global_list') {
            $filter = $_GET['filter'] ?? 'all';
            $allowedFilters = ['all', 'done', 'pending', 'missed'];
            if (!in_array($filter, $allowedFilters, true)) {
                $filter = 'all';
            }

            $conditions = [
                't.deletionStatus = 0',
                'c.majorType = u.majorType',
                'c.studyProgram = u.studyProgram',
                'c.classGroup = u.classGroup',
                'c.batchYear = u.batchYear'
            ];
            if ($filter === 'done') {
                $conditions[] = 'tc.completionId IS NOT NULL';
            } elseif ($filter === 'pending') {
                $conditions[] = 'tc.completionId IS NULL';
                $conditions[] = 't.dueDate >= NOW()';
            } elseif ($filter === 'missed') {
                $conditions[] = 'tc.completionId IS NULL';
                $conditions[] = 't.dueDate < NOW()';
            }

            $stmtGlobal = $pdo->prepare('SELECT t.taskId, t.taskTitle, t.dueDate, t.taskType, c.courseTitle
                FROM tasks t
                JOIN courses c ON c.courseId = t.courseId
                JOIN users u ON u.userId = :userId
                LEFT JOIN task_completions tc ON tc.taskId = t.taskId AND tc.userId = :completionUserId
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY t.dueDate ASC, t.taskTitle ASC');
            $stmtGlobal->execute(['userId' => $userId, 'completionUserId' => $userId]);
            echo json_encode(['success' => true, 'data' => ['tasks' => $stmtGlobal->fetchAll(PDO::FETCH_ASSOC), 'filter' => $filter]]);
            exit;
        }

        // 1. GET LIST TUGAS
        if ($action === 'list') {$courseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : 0;
            $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit    = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
            $offset   = ($page - 1) *$limit;
            $search   = isset($_GET['search']) ? trim($_GET['search']) : '';$sort     = isset($_GET['sort']) ? trim($_GET['sort']) : 'due_asc';

            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid']);
                exit;
            }

            $orderSql = "t.dueDate ASC";
            if ($sort === 'due_desc')$orderSql = "t.dueDate DESC";
            if ($sort === 'title_asc')$orderSql = "t.taskTitle ASC";
            if ($sort === 'title_desc')$orderSql = "t.taskTitle DESC";

            $searchSql = "";
            $params = [':courseId' =>$courseId];
            
            if ($search !== '') {$searchSql = " AND (t.taskTitle LIKE :searchTitle OR t.taskDescription LIKE :searchDescription) ";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            $stmtCount =$pdo->prepare("SELECT COUNT(taskId) FROM tasks t WHERE t.courseId = :courseId $searchSql");
            $stmtCount->execute($params);
            $totalTasks = (int)$stmtCount->fetchColumn();

                 $sql = "SELECT t.taskId, t.taskTitle, t.taskDescription, t.dueDate, t.taskType,
                          t.deletionStatus, t.deletedAt, du.userName AS deletedByUserName,
                          COALESCE(tc.completionId, 0) AS isCompleted
                    FROM tasks t
                    LEFT JOIN task_completions tc ON t.taskId = tc.taskId AND tc.userId = :userId
                      LEFT JOIN users du ON t.deletedByUserId = du.userId
                    WHERE t.courseId = :courseId $searchSql
                      ORDER BY t.deletionStatus ASC, $orderSql
                    LIMIT :limit OFFSET :offset";

            $stmtTasks = $pdo->prepare($sql);
            foreach ($params as$key => $val) {$stmtTasks->bindValue($key,$val);
            }
            $stmtTasks->bindValue(':userId', $userId, PDO::PARAM_INT);$stmtTasks->bindValue(':limit', $limit, PDO::PARAM_INT);$stmtTasks->bindValue(':offset', $offset, PDO::PARAM_INT);$stmtTasks->execute();
            $tasks =$stmtTasks->fetchAll(PDO::FETCH_ASSOC);

            // Menentukan Status Deadline (Tersedia, Selesai, Telat)
            $now = new DateTime();
            foreach ($tasks as &$t) {
                $dueDate = new DateTime($t['dueDate']);
                $t['isDeleted'] = (bool)$t['deletionStatus'];
                if ($t['isDeleted']) {
                    $t['statusBadge'] = 'Dihapus';
                    $t['deletedMessage'] = 'Dihapus oleh ' . ($t['deletedByUserName'] ?: 'Pengguna') . ' pada ' . $t['deletedAt'];
                    $t['isClickable'] = false;
                } elseif ($t['isCompleted']) {$t['statusBadge'] = 'Selesai';
                } elseif ($dueDate < $now) {$t['statusBadge'] = 'Telat';
                } else {
                    $t['statusBadge'] = 'Tersedia';
                }
                if (!$t['isDeleted']) {
                    $t['isClickable'] = true;
                }
                
                // Potong deskripsi jika terlalu panjang
                $t['taskDescription'] = strlen($t['taskDescription']) > 100 
                                      ? substr($t['taskDescription'], 0, 100) . '...'                                        :$t['taskDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'tasks' => $tasks,
                    'total' => $totalTasks,
                    'page'  => $page,
                    'limit' => $limit,
                    'hasMore' => ($offset + $limit) <$totalTasks
                ]
            ]);
            exit;
        }

        // 2. GET DETAIL TUGAS
        if ($action === 'detail') {$taskId = isset($_GET['taskId']) ? (int)$_GET['taskId'] : 0;
            if ($taskId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
                exit;
            }

            // Data Tugas, Pembuat, dan Editor
            $stmtTask =$pdo->prepare("
                SELECT t.*, 
                         c.courseCode, c.courseTitle,
                         u1.userName AS authorName, u1.avatarUrl AS authorAvatar, u1.roleLevel AS authorRole,
                      u2.userName AS editorName,
                        du.userName AS deletedByUserName
                FROM tasks t
                    LEFT JOIN courses c ON t.courseId = c.courseId
                LEFT JOIN users u1 ON t.createdByUserId = u1.userId
                LEFT JOIN users u2 ON t.lastEditedByUserId = u2.userId
                  LEFT JOIN users du ON t.deletedByUserId = du.userId
                WHERE t.taskId = :taskId
            ");
            $stmtTask->execute(['taskId' =>$taskId]);
            $task =$stmtTask->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
                exit;
            }

            // Data Lampiran (Multi-file)
            $stmtFiles =$pdo->prepare("SELECT fileId, filePath, fileName, fileSize FROM task_files WHERE taskId = :taskId");
            $stmtFiles->execute(['taskId' =>$taskId]);
            $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

            // Cek apakah user saat ini sudah menandai selesai
            $stmtCheckDone =$pdo->prepare("SELECT completionId FROM task_completions WHERE taskId = :taskId AND userId = :userId");
            $stmtCheckDone->execute(['taskId' => $taskId, 'userId' =>$userId]);
            $isCompleted = (bool)$stmtCheckDone->fetchColumn();

            // Daftar Mahasiswa yang sudah Selesai
            $stmtCompletions =$pdo->prepare("
                SELECT c.completedAt, u.userName, u.avatarUrl, u.roleLevel, u.hideCompletedIdentity
                FROM task_completions c
                LEFT JOIN users u ON c.userId = u.userId
                WHERE c.taskId = :taskId
                ORDER BY c.completedAt ASC
            ");
            $stmtCompletions->execute(['taskId' =>$taskId]);
            $completions =$stmtCompletions->fetchAll(PDO::FETCH_ASSOC);

            foreach ($completions as &$comp) {
                if ($comp['userName'] === null) {
                    $comp['userName'] = 'Pengguna Nonaktif';
                    $comp['avatarUrl'] = BASE_URL . 'public/assets/img/logo.png';
                    $comp['roleLevel'] = 'Keroco';
                } elseif ($comp['hideCompletedIdentity']) {$comp['userName'] = 'Mahasiswa Rahasia';
                    $comp['avatarUrl'] = BASE_URL . 'public/assets/img/logo.png';
                    $comp['roleLevel'] = 'Keroco';
                }
            }

            // Komentar Publik
            $stmtComments =$pdo->prepare("
                SELECT c.commentId, c.commentContent, c.createdAt, u.userName, u.avatarUrl, u.roleLevel, u.userId
                FROM task_comments c
                LEFT JOIN users u ON c.userId = u.userId
                WHERE c.taskId = :taskId
                ORDER BY c.createdAt ASC
            ");
            $stmtComments->execute(['taskId' =>$taskId]);
            $comments =$stmtComments->fetchAll(PDO::FETCH_ASSOC);

            foreach ($comments as &$comment) {
                if ($comment['userName'] === null) {
                    $comment['userName'] = 'Pengguna Nonaktif';
                    $comment['avatarUrl'] = BASE_URL . 'public/assets/img/logo.png';
                    $comment['roleLevel'] = 'Keroco';
                }
            }
            unset($comment);

            echo json_encode([
                'success' => true,
                'data' => [
                    'task' => $task,
                    'files' => $files,
                    'isCompleted' => $isCompleted,
                    'completions' => $completions,
                    'comments' => $comments,
                    'isDeleted' => (bool)$task['deletionStatus'],
                    'deletedMessage' => $task['deletionStatus']
                        ? 'Dihapus oleh ' . ($task['deletedByUserName'] ?: 'Pengguna') . ' pada ' . $task['deletedAt']
                        : null,
                    'canEdit' => !(bool)$task['deletionStatus'],
                    'isClickable' => !(bool)$task['deletionStatus']
                ]
            ]);
            exit;
        }
    }

    // ====================================================================================
    // POST REQUESTS (CREATE, TOGGLE COMPLETION, ADD COMMENT)
    // ====================================================================================
    if ($method === 'POST') {
        
        // 1. ADD COMMENT
        if ($action === 'add_comment') {$taskId = isset($_POST['taskId']) ? (int)$_POST['taskId'] : 0;
            $commentContent = isset($_POST['commentContent']) ? trim($_POST['commentContent']) : '';

            if ($taskId <= 0 || empty($commentContent)) {
                echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
                exit;
            }

            $stmtTaskExists = $pdo->prepare('SELECT taskId FROM tasks WHERE taskId = :taskId AND deletionStatus = 0');
            $stmtTaskExists->execute(['taskId' => $taskId]);
            if (!$stmtTaskExists->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
                exit;
            }

            $stmt =$pdo->prepare("INSERT INTO task_comments (taskId, userId, commentContent, createdAt) VALUES (:taskId, :userId, :content, NOW())");
            $stmt->execute([
                'taskId' => $taskId,
                'userId' => $userId,
                'content' => $commentContent
            ]);

            echo json_encode(['success' => true, 'message' => 'Komentar berhasil dikirim']);
            exit;
        }

        // 2. TOGGLE COMPLETION
        if ($action === 'toggle_completion') {$taskId = isset($_POST['taskId']) ? (int)$_POST['taskId'] : 0;
            if ($taskId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
                exit;
            }

            $stmtTaskExists = $pdo->prepare('SELECT taskId FROM tasks WHERE taskId = :taskId AND deletionStatus = 0');
            $stmtTaskExists->execute(['taskId' => $taskId]);
            if (!$stmtTaskExists->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
                exit;
            }

            $pdo->beginTransaction();
            
            $stmtCheck =$pdo->prepare("SELECT completionId FROM task_completions WHERE taskId = :taskId AND userId = :userId");
            $stmtCheck->execute(['taskId' => $taskId, 'userId' =>$userId]);
            $exists =$stmtCheck->fetchColumn();

            if ($exists) {
                $stmtDel =$pdo->prepare("DELETE FROM task_completions WHERE taskId = :taskId AND userId = :userId");
                $stmtDel->execute(['taskId' => $taskId, 'userId' =>$userId]);
                $message = 'Tugas dibatalkan selesai';$status = false;
            } else {
                $stmtIns =$pdo->prepare("INSERT INTO task_completions (taskId, userId, completedAt) VALUES (:taskId, :userId, NOW())");
                $stmtIns->execute(['taskId' => $taskId, 'userId' =>$userId]);
                $message = 'Tugas ditandai selesai';$status = true;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $message, 'isCompleted' =>$status]);
            exit;
        }

        // 3. CREATE TASK (Multi-File)
        if ($action === 'create') {
            $courseId        = (int)($_POST['courseId'] ?? 0);
            $semesterId      = (int)($_POST['semesterId'] ?? 0);
            $taskType        = trim($_POST['taskType'] ?? 'Tugas');
            $taskTitle       = trim($_POST['taskTitle'] ?? '');
            $taskDescription = trim($_POST['taskDescription'] ?? '');
            $dueDate         = trim($_POST['dueDate'] ?? '');

            if ($courseId <= 0 || $semesterId <= 0 || empty($taskTitle) || empty($dueDate)) {
                echo json_encode(['success' => false, 'message' => 'Input wajib belum diisi']);
                exit;
            }

            $stmtCourse = $pdo->prepare('SELECT courseId FROM courses WHERE courseId = :courseId AND semesterId = :semesterId');
            $stmtCourse->execute(['courseId' => $courseId, 'semesterId' => $semesterId]);
            if (!$stmtCourse->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Course dan semester tidak cocok']);
                exit;
            }

            $uploadDir = __DIR__ . '/../../public/uploads/tasks/';
            $pdo->beginTransaction();

            // Insert Task Record
            $stmtTask =$pdo->prepare("
                INSERT INTO tasks (courseId, semesterId, taskType, taskTitle, taskDescription, dueDate, createdByUserId, createdAt, updatedAt) 
                VALUES (:courseId, :semesterId, :taskType, :title, :desc, :due, :creator, NOW(), NOW())
            ");
            $stmtTask->execute([
                'courseId'   => $courseId,
                'semesterId' => $semesterId,
                'taskType'   => $taskType,
                'title'      => $taskTitle,
                'desc'       => $taskDescription,
                'due'        => $dueDate,
                'creator'    => $userId
            ]);
            $newTaskId =$pdo->lastInsertId();

            saveTaskAttachments($pdo, (int)$newTaskId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/tasks/');

            // Poin kontribusi secara otomatis akan terhitung di dashboard berdasarkan COUNT(taskId) di tabel tasks.
            // Tidak perlu ada update table `users` karena tidak ada field `points`.

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil dibuat. +1 Poin Kontribusi!']);
            exit;
        }
    }

    // ====================================================================================
    // PUT REQUESTS (UPDATE TASK)
    // ====================================================================================
    if ($method === 'PUT') {
        // Untuk multi-part via PUT di PHP, kita membaca dari POST dengan _method=PUT
        $taskId          = (int)($_POST['taskId'] ?? 0);
        $taskType        = trim($_POST['taskType'] ?? '');
        $taskTitle       = trim($_POST['taskTitle'] ?? '');
        $taskDescription = trim($_POST['taskDescription'] ?? '');
        $dueDate         = trim($_POST['dueDate'] ?? '');

        if ($taskId <= 0 || empty($taskTitle) || empty($dueDate)) {
            echo json_encode(['success' => false, 'message' => 'Input tidak lengkap']);
            exit;
        }

        $stmtTaskState = $pdo->prepare('SELECT deletionStatus FROM tasks WHERE taskId = :taskId');
        $stmtTaskState->execute(['taskId' => $taskId]);
        $taskState = $stmtTaskState->fetchColumn();
        if ($taskState === false) {
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }
        if ((int)$taskState === 1) {
            echo json_encode(['success' => false, 'message' => 'Tugas yang sudah dihapus tidak dapat diedit']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtUpdate =$pdo->prepare("
            UPDATE tasks 
            SET taskType = :taskType, taskTitle = :title, taskDescription = :desc, dueDate = :due, 
                lastEditedByUserId = :editor, updatedAt = NOW()
            WHERE taskId = :taskId AND deletionStatus = 0
        ");
        $stmtUpdate->execute([
            'taskType' => $taskType,
            'title'    => $taskTitle,
            'desc'     => $taskDescription,
            'due'      => $dueDate,
            'editor'   => $userId,
            'taskId'   => $taskId
        ]);

        saveTaskAttachments($pdo, $taskId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/tasks/');

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Tugas berhasil diperbarui']);
        exit;
    }

    // ====================================================================================
    // DELETE REQUESTS (SOFT DELETE TASK)
    // ====================================================================================
    if ($method === 'DELETE') {
        $rawInput = file_get_contents('php://input');$data = json_decode($rawInput, true) ?:$_POST;
        $taskId = (int)($data['taskId'] ?? 0);

        if ($taskId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtTask = $pdo->prepare("SELECT deletionStatus FROM tasks WHERE taskId = :taskId FOR UPDATE");
        $stmtTask->execute(['taskId' => $taskId]);
        $taskState = $stmtTask->fetchColumn();
        if ($taskState === false) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }
        if ((int)$taskState === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Tugas sudah dihapus']);
            exit;
        }

        $stmtFiles =$pdo->prepare("SELECT filePath FROM task_files WHERE taskId = :taskId");
        $stmtFiles->execute(['taskId' =>$taskId]);
        $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        $stmtDelTask =$pdo->prepare(
            "UPDATE tasks
             SET deletionStatus = 1, deletedByUserId = :userId, deletedAt = NOW(), updatedAt = NOW()
             WHERE taskId = :taskId AND deletionStatus = 0"
        );
        $stmtDelTask->execute(['taskId' => $taskId, 'userId' => $userId]);

        $pdo->commit();

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../../' . $file['filePath'];
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus.']);
        exit;
    }

    // Jika tidak ada method/action yang sesuai
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak dikenali']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {$pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}