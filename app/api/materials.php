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

require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/DriveManager.php';

header('Content-Type: application/json');

function saveMaterialAttachments(PDO $pdo, int $materialId, int $userId, array $files, ?string &$newlyCreatedFolderId = null, array &$newlyUploadedFileIds = []): void
{
    if (!DriveManager::hasFiles($files)) {
        return;
    }

    $driveManager = DriveManager::getInstance();

    // Ambil drive_folder_id yang sudah ada (jika ada)
    $stmtFolder = $pdo->prepare('SELECT drive_folder_id FROM learning_material WHERE materialId = :materialId');
    $stmtFolder->execute(['materialId' => $materialId]);
    $existingFolderId = $stmtFolder->fetchColumn() ?: null;

    $folderId = $driveManager->resolveEntityFolder('Materials', $existingFolderId, $userId, $materialId);

    if (empty($existingFolderId)) {
        $newlyCreatedFolderId = $folderId;
        $stmtUpdateFolder = $pdo->prepare('UPDATE learning_material SET drive_folder_id = :folderId WHERE materialId = :materialId');
        $stmtUpdateFolder->execute([
            'folderId'   => $folderId,
            'materialId' => $materialId
        ]);
    }

    $uploadedFiles = $driveManager->uploadFiles($folderId, $files);

    if (!empty($uploadedFiles)) {
        $stmtFile = $pdo->prepare(
            'INSERT INTO material_files (materialId, filePath, fileName, fileSize, uploadedAt)
             VALUES (:materialId, :filePath, :fileName, :fileSize, NOW())'
        );
        foreach ($uploadedFiles as $file) {
            $newlyUploadedFileIds[] = $file['fileId'];
            $stmtFile->execute([
                'materialId' => $materialId,
                'filePath'   => $file['filePath'],
                'fileName'   => $file['fileName'],
                'fileSize'   => $file['fileSize']
            ]);
        }
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

            if ($courseId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID Mata kuliah tidak valid']);
                exit;
            }

            // Validasi & Auto-Resolve semesterId dari tabel courses jika kosong
            $stmtCourse = $pdo->prepare('SELECT semesterId FROM courses WHERE courseId = :courseId LIMIT 1');
            $stmtCourse->execute(['courseId' => $courseId]);
            $courseSemesterId = $stmtCourse->fetchColumn();

            if ($courseSemesterId === false) {
                echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan']);
                exit;
            }

            if ($semesterId <= 0) {
                $semesterId = (int)$courseSemesterId;
            }

            // LOGIKA AUTO-TITLE: Jika judul kosong, ambil dari nama file pertama tanpa ekstensi
            if (empty($materialTitle)) {
                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    $firstFileName = $_FILES['attachments']['name'][0];
                    $materialTitle = pathinfo($firstFileName, PATHINFO_FILENAME);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Judul materi wajib diisi atau lampirkan minimal 1 file.']);
                    exit;
                }
            }

            // Cek apakah tabel learning_material memiliki kolom semesterId
            $hasSemesterIdCol = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM learning_material LIKE 'semesterId'");
                $hasSemesterIdCol = ($colCheck && $colCheck->rowCount() > 0);
            } catch (Throwable $t) {
                $hasSemesterIdCol = false;
            }

            $newlyCreatedFolderId = null;
            $newlyUploadedFileIds = [];
            $pdo->beginTransaction();

            try {
                // Insert Material Record secara dinamis sesuai struktur tabel
                if ($hasSemesterIdCol && $semesterId > 0) {
                    $stmtMaterial = $pdo->prepare("
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
                } else {
                    $stmtMaterial = $pdo->prepare("
                        INSERT INTO learning_material (courseId, materialTitle, materialDescription, uploadedByUserId, createdAt, updatedAt) 
                        VALUES (:courseId, :title, :desc, :creator, NOW(), NOW())
                    ");
                    $stmtMaterial->execute([
                        'courseId'   => $courseId,
                        'title'      => $materialTitle,
                        'desc'       => $materialDescription,
                        'creator'    => $userId
                    ]);
                }
                $newMaterialId = (int)$pdo->lastInsertId();

                saveMaterialAttachments($pdo, $newMaterialId, $userId, $_FILES['attachments'] ?? [], $newlyCreatedFolderId, $newlyUploadedFileIds);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Materi berhasil dibagikan. +1 Poin Kontribusi!']);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newlyCreatedFolderId !== null) {
                    try {
                        DriveManager::getInstance()->deleteFolder($newlyCreatedFolderId);
                    } catch (Throwable $ignored) {}
                } elseif (!empty($newlyUploadedFileIds)) {
                    foreach ($newlyUploadedFileIds as $fileId) {
                        try {
                            DriveManager::getInstance()->deleteFile($fileId);
                        } catch (Throwable $ignored) {}
                    }
                }
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan materi: ' . $e->getMessage()]);
                exit;
            }
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

        $newlyCreatedFolderId = null;
        $newlyUploadedFileIds = [];
        $pdo->beginTransaction();

        try {
            $stmtUpdate = $pdo->prepare("
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

            saveMaterialAttachments($pdo, $materialId, $userId, $_FILES['attachments'] ?? [], $newlyCreatedFolderId, $newlyUploadedFileIds);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Materi berhasil diperbarui']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newlyCreatedFolderId !== null) {
                try {
                    DriveManager::getInstance()->deleteFolder($newlyCreatedFolderId);
                } catch (Throwable $ignored) {}
            } elseif (!empty($newlyUploadedFileIds)) {
                foreach ($newlyUploadedFileIds as $fileId) {
                    try {
                        DriveManager::getInstance()->deleteFile($fileId);
                    } catch (Throwable $ignored) {}
                }
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui materi: ' . $e->getMessage()]);
            exit;
        }
    }

    // ====================================================================================
    // DELETE REQUESTS (SOFT DELETE MATERIAL & ATTACHMENT DELETION)
    // ====================================================================================
    if ($method === 'DELETE') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;

        if (($data['action'] ?? '') === 'hard_delete') {
            $materialId = (int)($data['materialId'] ?? 0);
            $stmtRole = $pdo->prepare('SELECT roleLevel FROM users WHERE userId = :userId');
            $stmtRole->execute(['userId' => $userId]);
            if (!in_array($stmtRole->fetchColumn(), ['Sepuh', 'Primordial'], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                exit;
            }

            $stmtMat = $pdo->prepare('SELECT drive_folder_id, deletionStatus FROM learning_material WHERE materialId = :materialId');
            $stmtMat->execute(['materialId' => $materialId]);
            $matRow = $stmtMat->fetch(PDO::FETCH_ASSOC);
            if (!$matRow || (int)$matRow['deletionStatus'] !== 1) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Hanya materi yang sudah dihapus sementara dapat dihapus permanen.']);
                exit;
            }

            // Hapus folder Drive secara permanen jika ada
            if (!empty($matRow['drive_folder_id'])) {
                DriveManager::getInstance()->deleteFolder($matRow['drive_folder_id']);
            }

            // Bersihkan file lokal legacy jika ada
            $stmtFiles = $pdo->prepare('SELECT filePath FROM material_files WHERE materialId = :materialId');
            $stmtFiles->execute(['materialId' => $materialId]);
            foreach ($stmtFiles->fetchAll(PDO::FETCH_COLUMN) as $filePath) {
                if (!filter_var($filePath, FILTER_VALIDATE_URL)) {
                    $fullPath = realpath(__DIR__ . '/../../' . $filePath);
                    $uploadRoot = realpath(__DIR__ . '/../../public/uploads/materials');
                    if ($fullPath && $uploadRoot && str_starts_with($fullPath, $uploadRoot . DIRECTORY_SEPARATOR) && file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }

            $stmtHardDelete = $pdo->prepare('DELETE FROM learning_material WHERE materialId = :materialId AND deletionStatus = 1');
            $stmtHardDelete->execute(['materialId' => $materialId]);
            echo json_encode(['success' => true, 'message' => 'Materi dihapus permanen.']);
            exit;
        }

        if (($data['action'] ?? '') === 'delete_attachment') {
            $fileId = (int)($data['fileId'] ?? 0);
            if ($fileId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'File ID tidak valid.']);
                exit;
            }

            $stmtFile = $pdo->prepare('
                SELECT mf.filePath, lm.deletionStatus 
                FROM material_files mf
                JOIN learning_material lm ON mf.materialId = lm.materialId
                WHERE mf.fileId = :fileId
            ');
            $stmtFile->execute(['fileId' => $fileId]);
            $fileRow = $stmtFile->fetch(PDO::FETCH_ASSOC);
            if (!$fileRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Lampiran tidak ditemukan.']);
                exit;
            }
            if ((int)$fileRow['deletionStatus'] === 1) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Lampiran pada materi yang telah dihapus tidak dapat diubah.']);
                exit;
            }

            $filePath = $fileRow['filePath'];

            // Hapus dari Google Drive
            DriveManager::getInstance()->deleteFile($filePath);

            // Bersihkan jika berkas merupakan file lokal legacy
            if (!filter_var($filePath, FILTER_VALIDATE_URL)) {
                $fullPath = realpath(__DIR__ . '/../../' . $filePath);
                $uploadRoot = realpath(__DIR__ . '/../../public/uploads/materials');
                if ($fullPath && $uploadRoot && str_starts_with($fullPath, $uploadRoot . DIRECTORY_SEPARATOR) && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $stmtDeleteFile = $pdo->prepare('DELETE FROM material_files WHERE fileId = :fileId');
            $stmtDeleteFile->execute(['fileId' => $fileId]);
            echo json_encode(['success' => true, 'message' => 'Lampiran berhasil dihapus.']);
            exit;
        }

        $materialId = (int)($data['materialId'] ?? 0);

        if ($materialId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Material ID tidak valid']);
            exit;
        }

        $pdo->beginTransaction();

        $stmtMaterial = $pdo->prepare("SELECT drive_folder_id, deletionStatus FROM learning_material WHERE materialId = :materialId FOR UPDATE");
        $stmtMaterial->execute(['materialId' => $materialId]);
        $matRow = $stmtMaterial->fetch(PDO::FETCH_ASSOC);
        if (!$matRow) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Materi tidak ditemukan']);
            exit;
        }
        if ((int)$matRow['deletionStatus'] === 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Materi sudah dihapus']);
            exit;
        }

        // Cascade Deletion Protocol: Hapus folder Google Drive jika ada
        if (!empty($matRow['drive_folder_id'])) {
            DriveManager::getInstance()->deleteFolder($matRow['drive_folder_id']);
        }

        $stmtDelMat = $pdo->prepare(
            "UPDATE learning_material
             SET deletionStatus = 1, deletedByUserId = :userId, deletedAt = NOW(), updatedAt = NOW()
             WHERE materialId = :materialId AND deletionStatus = 0"
        );
        $stmtDelMat->execute(['materialId' => $materialId, 'userId' => $userId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Materi berhasil dihapus.']);
        exit;
    }

    // Jika tidak ada method/action yang sesuai
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak dikenali']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API materials error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
    ]);
}