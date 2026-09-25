<?php
/**
 * ====================================================================================
 * MODULE: Backend API Materials (Learning Materials Management)
 * FILE LOCATION: app/api/materials.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini berfungsi sebagai API Endpoint terpusat untuk menangani seluruh operasi CRUD
 * data materi pembelajaran, pengunggahan multi-file lampiran, logika judul otomatis dari
 * nama file pertama tanpa ekstensi, pencatatan histori pengeditan, serta penghapusan data
 * secara permanen (Hard Delete) oleh pembuat materi.
 * 
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. learning_material (Main Table):
 *    - PK: materialId
 *    - FK: courseId -> courses.courseId (ON DELETE CASCADE)
 *    - FK: semesterId -> semesters.semesterId (ON DELETE CASCADE)
 *    - FK: uploadedByUserId -> users.userId (ON DELETE SET NULL)
 *    - FK: lastEditedByUserId -> users.userId (ON DELETE SET NULL)
 * 2. material_files (Child Table Multi-File):
 *    - PK: fileId
 *    - FK: materialId -> learning_material.materialId (ON DELETE CASCADE)
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/course_detail.js, public/js/modules/material_detail.js
 * - Views: app/views/material_detail.php, app/views/course_detail.php
 * - Helper / DB: app/api/db.php, app/config/config.php, app/api/helpers.php
 * 
 * CARA KERJA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi: Memeriksa session aktif $_SESSION['user_id']. Jika tidak ada, return HTTP 401 / JSON success: false.
 * 2. Identifikasi Request (Action / Method Routing):
 *    - GET:
 *      a) action = 'list': Mengambil daftar materi berdasarkan courseId dengan dukungan pagination/lazy loading, search, dan sorting (date_desc, date_asc, title_asc, title_desc).
 *      b) action = 'detail': Mengambil detail 1 materi berdasarkan materialId (termasuk data pengunggah, editor terakhir, dan daftar file dari material_files).
 * 
 *    - POST (Create Material):
 *      a) Menerima input: courseId, semesterId, materialTitle, materialDescription, dan $_FILES['attachments'] (multi-file).
 *      b) LOGIKA AUTO-TITLE: Jika `materialTitle` dikosongkan oleh user, sistem secara otomatis mengambil nama file pertama dari $_FILES['attachments'] lalu membuang ekstensi filenya.
 *      c) Memproses pengunggahan multi-file ke folder 'public/uploads/materials/'.
 *      d) Memasukkan data ke tabel 'learning_material' dengan uploadedByUserId = $_SESSION['user_id'].
 *      e) Memasukkan setiap path file terunggah ke tabel 'material_files'.
 *      f) [Aturan Poin]: Menambah +1 poin kontribusi pengguna (poin terhitung otomatis via COUNT record di dashboard).
 *      g) Return JSON success: true.
 * 
 *    - POST / PUT (Update Material):
 *      a) Menerima input data materi yang diperbarui & file lampiran baru jika ada.
 *      b) OTORISASI: Memeriksa apakah $_SESSION['user_id'] === uploadedByUserId (Hanya pengunggah asli / role Sepuh/Primordial yang boleh edit).
 *      c) Memperbarui data materi di tabel 'learning_material'.
 *      d) Mengisi kolom 'lastEditedByUserId' = $_SESSION['user_id'] dan 'updatedAt' = NOW().
 *      e) Menambahkan file baru ke 'material_files' jika pengguna mengunggah lampiran tambahan.
 * 
 *    - DELETE / POST _method=DELETE (Hard Delete Material):
 *      a) Menerima materialId.
 *      b) OTORISASI STRICT: Memeriksa apakah $_SESSION['user_id'] === uploadedByUserId. Hanya author yang boleh menghapus (atau Primordial/Sepuh).
 *      c) Mengambil semua file Path terkait dari 'material_files'.
 *      d) Menghapus file fisik dari direktori server menggunakan pustaka unlink().
 *      e) Mengeksekusi SQL HARD DELETE pada tabel 'learning_material'. Karena FK diset ON DELETE CASCADE, seluruh data di 'material_files' akan terhapus otomatis di database.
 *      f) [Catatan Poin]: Poin kontribusi author TIDAK berkurang saat delete.
 * 
 * ATURAN BISNIS & SECURITY (BUSINESS RULES):
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

function saveMaterialAttachments(PDO $pdo, int $materialId, array $files, string $uploadDir): void
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat');
    }

    $allowedMimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip'
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
                'INSERT INTO material_files (materialId, filePath, fileName, fileSize, uploadedAt)
                 VALUES (:materialId, :filePath, :fileName, :fileSize, NOW())'
            );
            $stmtFile->execute([
                'materialId' => $materialId,
                'filePath' => 'public/uploads/materials/' . $safeName,
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
        
        // 1. GET LIST MATERI
        if ($action === 'list') {$courseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : 0;
            $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit    = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
            $offset   = ($page - 1) *$limit;
            $search   = isset($_GET['search']) ? trim($_GET['search']) : '';$sort     = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';

            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course ID tidak valid']);
                exit;
            }

            $orderSql = "m.createdAt DESC";
            if ($sort === 'date_asc')$orderSql = "m.createdAt ASC";
            if ($sort === 'title_asc')$orderSql = "m.materialTitle ASC";
            if ($sort === 'title_desc')$orderSql = "m.materialTitle DESC";

            $searchSql = "";
            $params = [':courseId' =>$courseId];
            
            if ($search !== '') {$searchSql = " AND (m.materialTitle LIKE :searchTitle OR m.materialDescription LIKE :searchDescription) ";
                $params[':searchTitle'] = "%$search%";
                $params[':searchDescription'] = "%$search%";
            }

            $stmtCount =$pdo->prepare("SELECT COUNT(materialId) FROM learning_material m WHERE m.courseId = :courseId $searchSql");
            $stmtCount->execute($params);
            $totalMaterials = (int)$stmtCount->fetchColumn();

                 $sql = "SELECT m.materialId, m.materialTitle, m.materialDescription, m.createdAt,
                          m.deletionStatus, m.deletedAt, du.userName AS deletedByName,
                          u.userName AS authorName, u.avatarUrl AS authorAvatar
                    FROM learning_material m
                    LEFT JOIN users u ON m.uploadedByUserId = u.userId
                      LEFT JOIN users du ON m.deletedByUserId = du.userId
                    WHERE m.courseId = :courseId $searchSql
                      ORDER BY m.deletionStatus ASC, $orderSql
                    LIMIT :limit OFFSET :offset";

            $stmtMaterials = $pdo->prepare($sql);
            foreach ($params as$key => $val) {$stmtMaterials->bindValue($key,$val);
            }
            $stmtMaterials->bindValue(':limit', $limit, PDO::PARAM_INT);$stmtMaterials->bindValue(':offset', $offset, PDO::PARAM_INT);$stmtMaterials->execute();
            $materials =$stmtMaterials->fetchAll(PDO::FETCH_ASSOC);

            // Potong deskripsi jika terlalu panjang untuk tampilan card
            foreach ($materials as &$m) {
                $m['isDeleted'] = (bool)$m['deletionStatus'];
                if ($m['isDeleted']) {
                    $m['deletedMessage'] = 'Dihapus oleh ' . ($m['deletedByName'] ?: 'Pengguna') . ' pada ' . $m['deletedAt'];
                    $m['isClickable'] = false;
                } else {
                    $m['isClickable'] = true;
                }
                $m['materialDescription'] = strlen($m['materialDescription']) > 100 
                                          ? substr($m['materialDescription'], 0, 100) . '...'                                            :$m['materialDescription'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'materials' => $materials,
                    'total' => $totalMaterials,
                    'page'  => $page,
                    'limit' => $limit,
                    'hasMore' => ($offset + $limit) <$totalMaterials
                ]
            ]);
            exit;
        }

        // 2. GET DETAIL MATERI
        if ($action === 'detail') {$materialId = isset($_GET['materialId']) ? (int)$_GET['materialId'] : 0;
            if ($materialId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Material ID tidak valid']);
                exit;
            }

            // Data Materi, Pembuat, dan Editor
            $stmtMaterial =$pdo->prepare("
                SELECT m.*, 
                      c.courseCode, c.courseTitle,
                       u1.userName AS authorName, u1.avatarUrl AS authorAvatar, u1.roleLevel AS authorRole,
                      u2.userName AS editorName,
                      du.userName AS deletedByName
                FROM learning_material m
                  LEFT JOIN courses c ON m.courseId = c.courseId
                LEFT JOIN users u1 ON m.uploadedByUserId = u1.userId
                LEFT JOIN users u2 ON m.lastEditedByUserId = u2.userId
                  LEFT JOIN users du ON m.deletedByUserId = du.userId
                WHERE m.materialId = :materialId
            ");
            $stmtMaterial->execute(['materialId' =>$materialId]);
            $material =$stmtMaterial->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                echo json_encode(['success' => false, 'message' => 'Materi tidak ditemukan']);
                exit;
            }

            // Data Lampiran (Multi-file)
            $stmtFiles =$pdo->prepare("SELECT fileId, filePath, fileName, fileSize FROM material_files WHERE materialId = :materialId");
            $stmtFiles->execute(['materialId' =>$materialId]);
            $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'material' => $material,
                    'files' => $files,
                    'isDeleted' => (bool)$material['deletionStatus'],
                    'deletedMessage' => $material['deletionStatus']
                        ? 'Dihapus oleh ' . ($material['deletedByName'] ?: 'Pengguna') . ' pada ' . $material['deletedAt']
                        : null,
                    'canEdit' => !(bool)$material['deletionStatus'],
                    'isClickable' => !(bool)$material['deletionStatus']
                ]
            ]);
            exit;
        }
    }

    // ====================================================================================
    // POST REQUESTS (CREATE MATERIAL)
    // ====================================================================================
    if ($method === 'POST') {
        
        if ($action === 'create') {
            $courseId            = (int)($_POST['courseId'] ?? 0);
            $semesterId          = (int)($_POST['semesterId'] ?? 0);
            $materialTitle       = trim($_POST['materialTitle'] ?? '');
            $materialDescription = trim($_POST['materialDescription'] ?? '');

            if ($courseId <= 0 || $semesterId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data Course/Semester tidak valid']);
                exit;
            }

            $stmtCourse = $pdo->prepare('SELECT courseId FROM courses WHERE courseId = :courseId AND semesterId = :semesterId');
            $stmtCourse->execute(['courseId' => $courseId, 'semesterId' => $semesterId]);
            if (!$stmtCourse->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Course dan semester tidak cocok']);
                exit;
            }

            // LOGIKA AUTO-TITLE: Jika judul kosong, ambil dari nama file pertama tanpa ekstensi
            if (empty($materialTitle)) {
                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    $firstFileName =$_FILES['attachments']['name'][0];
                    $materialTitle = pathinfo($firstFileName, PATHINFO_FILENAME);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Judul materi wajib diisi atau lampirkan minimal 1 file.']);
                    exit;
                }
            }

            $pdo->beginTransaction();

            // Insert Material Record
            $stmtMaterial =$pdo->prepare("
                INSERT INTO learning_material (courseId, semesterId, materialTitle, materialDescription, uploadedByUserId, createdAt, updatedAt) 
                VALUES (:courseId, :semesterId, :title, :desc, :creator, NOW(), NOW())
            ");
            $stmtMaterial->execute([
                'courseId'   => $courseId,
                'semesterId' => $semesterId,
                'title'      => $materialTitle,
                'desc'       => $materialDescription,
                'creator'    => $userId
            ]);
            $newMaterialId =$pdo->lastInsertId();

            saveMaterialAttachments($pdo, (int)$newMaterialId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/materials/');

            // Poin kontribusi (+1) secara otomatis terhitung di query dashboard berdasarkan COUNT(materialId)
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Materi berhasil dibagikan. +1 Poin Kontribusi!']);
            exit;
        }
    }

    // ====================================================================================
    // PUT REQUESTS (UPDATE MATERIAL)
    // ====================================================================================
    if ($method === 'PUT') {
        $materialId          = (int)($_POST['materialId'] ?? 0);
        $materialTitle       = trim($_POST['materialTitle'] ?? '');
        $materialDescription = trim($_POST['materialDescription'] ?? '');

        if ($materialId <= 0 || empty($materialTitle)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        $stmtMaterialState = $pdo->prepare('SELECT deletionStatus FROM learning_material WHERE materialId = :materialId');
        $stmtMaterialState->execute(['materialId' => $materialId]);
        $materialState = $stmtMaterialState->fetchColumn();
        if ($materialState === false) {
            echo json_encode(['success' => false, 'message' => 'Materi tidak ditemukan']);
            exit;
        }
        if ((int)$materialState === 1) {
            echo json_encode(['success' => false, 'message' => 'Materi yang sudah dihapus tidak dapat diedit']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtUpdate =$pdo->prepare("
            UPDATE learning_material 
            SET materialTitle = :title, materialDescription = :desc, 
                lastEditedByUserId = :editor, updatedAt = NOW()
            WHERE materialId = :materialId AND deletionStatus = 0
        ");
        $stmtUpdate->execute([
            'title'      => $materialTitle,
            'desc'       => $materialDescription,
            'editor'     => $userId,
            'materialId' => $materialId
        ]);

        saveMaterialAttachments($pdo, $materialId, $_FILES['attachments'] ?? [], __DIR__ . '/../../public/uploads/materials/');

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Materi berhasil diperbarui']);
        exit;
    }

    // ====================================================================================
    // DELETE REQUESTS (SOFT DELETE MATERIAL)
    // ====================================================================================
    if ($method === 'DELETE') {
        $rawInput = file_get_contents('php://input');$data = json_decode($rawInput, true) ?:$_POST;
        $materialId = (int)($data['materialId'] ?? 0);

        if ($materialId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Material ID tidak valid']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtMaterial = $pdo->prepare("SELECT deletionStatus FROM learning_material WHERE materialId = :materialId FOR UPDATE");
        $stmtMaterial->execute(['materialId' => $materialId]);
        $materialState = $stmtMaterial->fetchColumn();
        if ($materialState === false) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Materi tidak ditemukan']);
            exit;
        }
        if ((int)$materialState === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Materi sudah dihapus']);
            exit;
        }

        $stmtFiles =$pdo->prepare("SELECT filePath FROM material_files WHERE materialId = :materialId");
        $stmtFiles->execute(['materialId' =>$materialId]);
        $files =$stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        $stmtDelMat =$pdo->prepare(
            "UPDATE learning_material
             SET deletionStatus = 1, deletedByUserId = :userId, deletedAt = NOW(), updatedAt = NOW()
             WHERE materialId = :materialId AND deletionStatus = 0"
        );
        $stmtDelMat->execute(['materialId' => $materialId, 'userId' => $userId]);

        $pdo->commit();

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/../../' . $file['filePath'];
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Materi berhasil dihapus.']);
        exit;
    }

    // Jika tidak ada method/action yang sesuai
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak dikenali']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {$pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}