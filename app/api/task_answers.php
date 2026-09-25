<?php
/**
 * ====================================================================================
 * MODULE: Backend API Task Answers (Sharing Answer Center Management)
 * FILE LOCATION: app/api/task_answers.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini berfungsi sebagai API Endpoint terpusat untuk menangani seluruh operasi CRUD
 * data sharing jawaban tugas, pengunggahan multi-file lampiran jawaban, pembacaan publik 
 * tanpa syarat menyelesaikan tugas terlebih dahulu, serta pembaruan dan penghapusan data 
 * yang dibatasi HANYA untuk author/pembuat aslinya dengan mekanisme Full Hard-Delete.
 * 
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. task_shared_answers (Main Table):
 *    - PK: answerId
 *    - FK: taskId -> tasks.taskId (ON DELETE CASCADE)
 *    - FK: userId -> users.userId (ON DELETE CASCADE)
 * 2. task_shared_answer_files (Child Table Multi-File):
 *    - PK: fileId
 *    - FK: answerId -> task_shared_answers.answerId (ON DELETE CASCADE)
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/task_answers.js, public/js/modules/task_detail.js
 * - Views: app/views/task_answers.php, app/views/task_detail.php
 * - Helper / DB: app/api/db.php, app/config/config.php, app/api/helpers.php
 * 
 * CARA KERJA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi: Memeriksa session aktif $_SESSION['user_id']. Jika tidak ada, return HTTP 401 / JSON.
 * 2. Identifikasi Request (Action / Method Routing):
 *    - GET:
 *      a) action = 'list': Mengambil seluruh daftar jawaban yang dibagikan untuk taskId tertentu. 
 *         Dapat diakses oleh siapa saja (publik terautentikasi) tanpa syarat harus menyelesaikan tugas dulu.
 *      b) action = 'detail': Mengambil detail 1 postingan jawaban beserta daftar file lampirannya.
 * 
 *    - POST (Create Shared Answer):
 *      a) Menerima input: taskId, answerTitle, answerNotes, dan $_FILES['attachments'] (multi-file).
 *      b) Memproses pengunggahan multi-file ke folder 'public/uploads/answers/'.
 *      c) Memasukkan data ke tabel 'task_shared_answers' dengan userId = $_SESSION['user_id'].
 *      d) Memasukkan setiap path file terunggah ke tabel 'task_shared_answer_files'.
 *      e) [Aturan Poin]: Menambah +1 poin kontribusi pengguna (terhitung otomatis di dashboard).
 * 
 *    - PUT / POST _method=PUT (Update Shared Answer):
 *      a) OTORISASI KETAT: Hanya pembuat asli (`userId === $_SESSION['user_id']`) yang boleh mengedit.
 *      b) Memperbarui data judul/catatan dan menambah file lampiran baru jika ada.
 * 
 *    - DELETE / POST _method=DELETE (Full Hard Delete Shared Answer):
 *      a) OTORISASI KETAT: Hanya pembuat asli (`userId === $_SESSION['user_id']`) yang boleh menghapus.
 *      b) Mengambil semua file path terkait dari 'task_shared_answer_files', lalu hapus file fisik via unlink().
 *      c) Menghapus baris data secara permanen dari tabel 'task_shared_answers' (Cascade menghapus file relasi).
 *      d) [Catatan Poin]: Poin kontribusi author TIDAK berkurang saat delete.
 * 
 * ATURAN BISNIS & SECURITY (BUSINESS RULES):
 * - Akses lihat dan buat jawaban terbuka untuk semua user terautentikasi (tanpa syarat selesai tugas).
 * - Akses Edit dan Delete HANYA untuk author aslinya (`userId === $_SESSION['user_id']`). User lain ditolak.
 * - Gunakan PDO Prepared Statements pada semua kueri SQL.
 * - Format output JSON konsisten: ['success' => bool, 'message' => string, 'data' => mixed].
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

function saveAnswerAttachments(PDO $pdo, int $answerId, array $files, string $uploadDir): void
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat');
    }

    $allowedMimeTypes = [
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'zip' => 'application/zip'
    ];
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
            if (!is_uploaded_file($tmpName) || $fileSize <= 0 || $fileSize > 10 * 1024 * 1024 ||
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
                'INSERT INTO task_shared_answer_files (answerId, filePath, fileName, fileSize, uploadedAt)
                 VALUES (:answerId, :filePath, :fileName, :fileSize, NOW())'
            );
            $stmtFile->execute([
                'answerId' => $answerId,
                'filePath' => 'public/uploads/answers/' . $safeName,
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

// 1. OTENTIKASI & SETUP VARIABEL
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method =$_SERVER['REQUEST_METHOD'];

$stmtRole = $pdo->prepare('SELECT roleLevel FROM users WHERE userId = :userId');
$stmtRole->execute(['userId' => $userId]);
$canDeleteAnyAnswer = strtolower((string)$stmtRole->fetchColumn()) === 'primordial';

// Handle Method Override untuk form-data multipart (karena PUT murni PHP sulit membaca $_FILES)
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    // ====================================================================================
    // GET REQUESTS
    // ====================================================================================
    if ($method === 'GET') {
        
        // A. GET LIST JAWABAN (Semua orang bisa melihat)
        if ($action === 'list') {$taskId = isset($_GET['taskId']) ? (int)$_GET['taskId'] : 0;
            
            if ($taskId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
                exit;
            }

            $stmtTask = $pdo->prepare('SELECT taskId FROM tasks WHERE taskId = :taskId AND deletionStatus = 0');
            $stmtTask->execute(['taskId' => $taskId]);
            if (!$stmtTask->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan atau sudah dihapus']);
                exit;
            }

            // Ambil daftar jawaban beserta informasi author
            $stmtAnswers =$pdo->prepare("
                SELECT a.answerId, a.taskId, a.answerTitle, a.answerNotes, a.createdAt, a.updatedAt,
                      u.userName, u.avatarUrl, u.roleLevel, u.userId AS authorId,
                      CASE WHEN a.userId = :currentUserId OR :isPrimordial = 1 THEN 1 ELSE 0 END AS canDelete
                FROM task_shared_answers a
                LEFT JOIN users u ON a.userId = u.userId
                WHERE a.taskId = :taskId
                ORDER BY a.createdAt DESC
            ");
            $stmtAnswers->execute([
                'taskId' => $taskId,
                'currentUserId' => $userId,
                'isPrimordial' => $canDeleteAnyAnswer ? 1 : 0
            ]);
            $answers =$stmtAnswers->fetchAll(PDO::FETCH_ASSOC);

            foreach ($answers as &$answer) {
                if ($answer['authorId'] === null) {
                    $answer['userName'] = 'Pengguna Nonaktif';
                    $answer['avatarUrl'] = BASE_URL . 'public/assets/img/logo.png';
                    $answer['roleLevel'] = 'Keroco';
                }
            }
            unset($answer);

            // Jika ada jawaban, kita ambil juga file lampirannya untuk dilampirkan ke setiap jawaban
            if (!empty($answers)) {
                $answerIds = array_column($answers, 'answerId');
                $inQuery = implode(',', array_fill(0, count($answerIds), '?'));
                
                $stmtFiles =$pdo->prepare("SELECT fileId, answerId, filePath, fileName, fileSize FROM task_shared_answer_files WHERE answerId IN ($inQuery)");
                $stmtFiles->execute($answerIds);
                $allFiles =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

                // Mengelompokkan file berdasarkan answerId
                $filesGrouped = [];
                foreach ($allFiles as $file) {$filesGrouped[$file['answerId']][] =$file;
                }

                // Menyisipkan file ke dalam array answers dan memotong notes jika kepanjangan
                foreach ($answers as &$ans) {$ans['files'] = $filesGrouped[$ans['answerId']] ?? [];
                    $ans['answerNotes'] = strlen((string)$ans['answerNotes']) > 150 
                                        ? substr($ans['answerNotes'], 0, 150) . '...'                                          :$ans['answerNotes'];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'answers' => $answers
                ]
            ]);
            exit;
        }

        // B. GET DETAIL 1 JAWABAN
        if ($action === 'detail') {$answerId = isset($_GET['answerId']) ? (int)$_GET['answerId'] : 0;
            
            if ($answerId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Answer ID tidak valid']);
                exit;
            }

            $stmtDetail =$pdo->prepare("
                SELECT a.*, u.userName, u.avatarUrl, u.roleLevel, u.userId AS authorId,
                       CASE WHEN a.userId = :currentUserId OR :isPrimordial = 1 THEN 1 ELSE 0 END AS canDelete
                FROM task_shared_answers a
                LEFT JOIN users u ON a.userId = u.userId
                JOIN tasks t ON a.taskId = t.taskId AND t.deletionStatus = 0
                WHERE a.answerId = :answerId
            ");
            $stmtDetail->execute([
                'answerId' => $answerId,
                'currentUserId' => $userId,
                'isPrimordial' => $canDeleteAnyAnswer ? 1 : 0
            ]);
            $answer =$stmtDetail->fetch(PDO::FETCH_ASSOC);

            if (!$answer) {
                echo json_encode(['success' => false, 'message' => 'Jawaban tidak ditemukan']);
                exit;
            }

            if ($answer['authorId'] === null) {
                $answer['userName'] = 'Pengguna Nonaktif';
                $answer['avatarUrl'] = BASE_URL . 'public/assets/img/logo.png';
                $answer['roleLevel'] = 'Keroco';
            }

            $stmtFiles =$pdo->prepare("SELECT fileId, filePath, fileName, fileSize FROM task_shared_answer_files WHERE answerId = :answerId");
            $stmtFiles->execute(['answerId' =>$answerId]);
            $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'answer' => $answer,
                    'files'  => $files,
                    'canEdit' => ((int)$answer['authorId'] === $userId)
                ]
            ]);
            exit;
        }
    }

    // ====================================================================================
    // POST REQUESTS (CREATE)
    // ====================================================================================
    if ($method === 'POST') {
        
        if ($action === 'create') {
            $taskId      = (int)($_POST['taskId'] ?? 0);
            $answerTitle = trim($_POST['answerTitle'] ?? '');
            $answerNotes = trim($_POST['answerNotes'] ?? '');

            if ($taskId <= 0 || empty($answerTitle)) {
                echo json_encode(['success' => false, 'message' => 'Task ID dan Judul Jawaban wajib diisi']);
                exit;
            }

            $stmtTask = $pdo->prepare('SELECT taskId FROM tasks WHERE taskId = :taskId AND deletionStatus = 0');
            $stmtTask->execute(['taskId' => $taskId]);
            if (!$stmtTask->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan atau sudah dihapus']);
                exit;
            }

            $pdo->beginTransaction();

            // Insert Row Jawaban Baru
            $stmtAns =$pdo->prepare("
                INSERT INTO task_shared_answers (taskId, userId, answerTitle, answerNotes, createdAt, updatedAt) 
                VALUES (:taskId, :userId, :title, :notes, NOW(), NOW())
            ");
            $stmtAns->execute([
                'taskId' => $taskId,
                'userId' => $userId,
                'title'  => $answerTitle,
                'notes'  => $answerNotes
            ]);
            $newAnswerId =$pdo->lastInsertId();

            saveAnswerAttachments($pdo, (int)$newAnswerId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/answers/');
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Jawaban berhasil dibagikan. +1 Poin Kontribusi!']);
            exit;
        }
    }

    // ====================================================================================
    // PUT REQUESTS (UPDATE)
    // ====================================================================================
    if ($method === 'PUT') {
        
        $answerId    = (int)($_POST['answerId'] ?? 0);
        $answerTitle = trim($_POST['answerTitle'] ?? '');
        $answerNotes = trim($_POST['answerNotes'] ?? '');

        if ($answerId <= 0 || empty($answerTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        // OTORISASI: Hanya pembuat asli yang boleh mengedit
        $stmtAuth =$pdo->prepare("SELECT userId FROM task_shared_answers WHERE answerId = :answerId");
        $stmtAuth->execute(['answerId' =>$answerId]);
        $authorId =$stmtAuth->fetchColumn();

        if ($authorId === false) {
            echo json_encode(['success' => false, 'message' => 'Jawaban tidak ditemukan']);
            exit;
        }
        if ((int)$authorId !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda bukan pembuat jawaban ini.']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtUpdate =$pdo->prepare("
            UPDATE task_shared_answers 
            SET answerTitle = :title, answerNotes = :notes, updatedAt = NOW()
            WHERE answerId = :answerId
        ");
        $stmtUpdate->execute([
            'title'    => $answerTitle,
            'notes'    => $answerNotes,
            'answerId' => $answerId
        ]);

        saveAnswerAttachments($pdo, $answerId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/answers/');

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Jawaban berhasil diperbarui']);
        exit;
    }

    // ====================================================================================
    // DELETE REQUESTS (FULL HARD-DELETE)
    // ====================================================================================
    if ($method === 'DELETE') {
        
        $rawInput = file_get_contents('php://input');$data = json_decode($rawInput, true) ?:$_POST;
        $answerId = (int)($data['answerId'] ?? 0);

        if ($answerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Answer ID tidak valid']);
            exit;
        }

        // OTORISASI: Hanya pembuat asli yang boleh menghapus
        $stmtAuth =$pdo->prepare("SELECT userId FROM task_shared_answers WHERE answerId = :answerId");
        $stmtAuth->execute(['answerId' =>$answerId]);
        $authorId =$stmtAuth->fetchColumn();

        if ($authorId === false) {
            echo json_encode(['success' => false, 'message' => 'Jawaban tidak ditemukan']);
            exit;
        }
        if ((int)$authorId !== $userId && !$canDeleteAnyAnswer) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak berhak menghapus jawaban ini.']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Ambil list file terkait untuk dihapus fisiknya via unlink()
        $stmtFiles =$pdo->prepare("SELECT filePath FROM task_shared_answer_files WHERE answerId = :answerId");
        $stmtFiles->execute(['answerId' =>$answerId]);
        $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        // 2. HARD DELETE baris data di database
        // Karena FK diset ON DELETE CASCADE, menghapus row ini otomatis menghapus data anak di task_shared_answer_files
        $stmtDel =$pdo->prepare("DELETE FROM task_shared_answers WHERE answerId = :answerId");
        $stmtDel->execute(['answerId' =>$answerId]);

        $pdo->commit();
        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../../' . $file['filePath'];
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Jawaban beserta lampirannya berhasil dihapus permanen.']);
        exit;
    }

    // Jika tidak ada request method atau action yang sesuai
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak dikenali']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {$pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}