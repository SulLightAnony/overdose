<?php
require_once __DIR__ . '/../config/config.php';
init_secure_session();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/DriveManager.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role_level'] ?? 'Keroco';
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($rawInput) ? $rawInput : $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');
$isAdmin = in_array($userRole, ['Sepuh', 'Primordial'], true);

function getNewsColumns(PDO $pdo): array
{
    static $cols = null;
    if ($cols === null) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM news");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            $cols = [];
        }
    }
    return $cols;
}

function validateUploadedImage(array $file): void
{
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return;
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah berkas gambar.');
    }
    $maxSize = 1024 * 1024; // 1 MB
    if ((int)$file['size'] > $maxSize) {
        throw new RuntimeException('Ukuran gambar melebihi batas maksimal 1 MB.');
    }
    $cleanName = basename((string)$file['name']);
    $ext = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Format berkas tidak diizinkan. Hanya file gambar (JPG, PNG, WEBP, GIF).');
    }
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime = false;
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    $allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp', 'image/gif'];
    if ($mime && !in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Konten berkas bukan gambar yang valid.');
    }
}

function uploadNewsImageToDrive(int $userId, int $newsId, array $file, ?string $existingFolderId = null, string $category = 'News'): array
{
    $driveManager = DriveManager::getInstance();
    $folderId = $driveManager->resolveEntityFolder($category, $existingFolderId, $userId, $newsId);

    $formattedFiles = [
        'name'     => [$file['name']],
        'type'     => [$file['type'] ?? 'image/jpeg'],
        'tmp_name' => [$file['tmp_name']],
        'error'    => [$file['error']],
        'size'     => [$file['size']]
    ];

    $uploaded = $driveManager->uploadFiles($folderId, $formattedFiles);
    if (empty($uploaded)) {
        throw new RuntimeException('Gagal mengunggah gambar ke Google Drive.');
    }

    $fileId = $uploaded[0]['fileId'];
    $imageUrl = "https://drive.google.com/thumbnail?id={$fileId}&sz=w1200";

    return [
        'fileId'   => $fileId,
        'imageUrl' => $imageUrl,
        'folderId' => $folderId
    ];
}

try {
    $cols = getNewsColumns($pdo);
    $hasImageCols = in_array('image_url', $cols, true);

    if ($method === 'GET' && $action === 'list') {
        $imageSelect = $hasImageCols ? 'n.image_url, n.drive_file_id, n.drive_folder_id,' : 'NULL AS image_url, NULL AS drive_file_id, NULL AS drive_folder_id,';
        $stmt = $pdo->query("SELECT n.id, n.user_id, n.title, n.content, {$imageSelect} n.created_at,
                                    u.userName, u.avatarUrl, u.roleLevel
                             FROM news n 
                             LEFT JOIN users u ON u.userId = n.user_id 
                             ORDER BY n.created_at DESC");
        echo json_encode(['success' => true, 'data' => ['news' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || $content === '' || mb_strlen($title) > 200) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Judul dan isi berita wajib diisi (judul maksimal 200 karakter).']);
            exit;
        }

        $hasImage = isset($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasImage) {
            validateUploadedImage($_FILES['image']);
        }

        $stmt = $pdo->prepare('INSERT INTO news (user_id, title, content) VALUES (:userId, :title, :content)');
        $stmt->execute(['userId' => $userId, 'title' => $title, 'content' => $content]);
        $newId = (int)$pdo->lastInsertId();

        if ($hasImage && $hasImageCols) {
            $uploadRes = uploadNewsImageToDrive($userId, $newId, $_FILES['image'], null, 'News');
            $upStmt = $pdo->prepare('UPDATE news SET image_url = :img, drive_file_id = :fid, drive_folder_id = :fld WHERE id = :id');
            $upStmt->execute([
                'img' => $uploadRes['imageUrl'],
                'fid' => $uploadRes['fileId'],
                'fld' => $uploadRes['folderId'],
                'id'  => $newId
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Berita berhasil diterbitkan.']);
        exit;
    }

    if ($method === 'POST' && $action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($id <= 0 || $title === '' || $content === '' || mb_strlen($title) > 200) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Data berita tidak valid.']);
            exit;
        }

        $selectQuery = $hasImageCols ? 'SELECT id, user_id, drive_folder_id, drive_file_id FROM news WHERE id = :id' : 'SELECT id, user_id FROM news WHERE id = :id';
        $fetch = $pdo->prepare($selectQuery);
        $fetch->execute(['id' => $id]);
        $existing = $fetch->fetch(PDO::FETCH_ASSOC);

        if (!$existing || (!$isAdmin && (int)$existing['user_id'] !== $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Berita tidak ditemukan atau Anda tidak berhak mengeditnya.']);
            exit;
        }

        $removeImage = !empty($input['remove_image']) && $input['remove_image'] !== '0';
        $hasNewImage = isset($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($removeImage && !$hasNewImage && $hasImageCols) {
            if (!empty($existing['drive_file_id'])) {
                try {
                    DriveManager::getInstance()->deleteFile($existing['drive_file_id']);
                } catch (Throwable $e) {}
            }
            $pdo->prepare('UPDATE news SET image_url = NULL, drive_file_id = NULL WHERE id = :id')->execute(['id' => $id]);
        }

        if ($hasNewImage && $hasImageCols) {
            validateUploadedImage($_FILES['image']);
            if (!empty($existing['drive_file_id'])) {
                try {
                    DriveManager::getInstance()->deleteFile($existing['drive_file_id']);
                } catch (Throwable $e) {}
            }
            $uploadRes = uploadNewsImageToDrive($userId, $id, $_FILES['image'], $existing['drive_folder_id'] ?? null, 'News');
            $pdo->prepare('UPDATE news SET image_url = :img, drive_file_id = :fid, drive_folder_id = :fld WHERE id = :id')
                ->execute([
                    'img' => $uploadRes['imageUrl'],
                    'fid' => $uploadRes['fileId'],
                    'fld' => $uploadRes['folderId'],
                    'id'  => $id
                ]);
        }

        $stmt = $pdo->prepare('UPDATE news SET title = :title, content = :content WHERE id = :id');
        $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Berita berhasil diperbarui.']);
        exit;
    }

    if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
        $id = (int)($input['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID berita tidak valid.']);
            exit;
        }

        $selectQuery = $hasImageCols ? 'SELECT id, user_id, drive_folder_id, drive_file_id FROM news WHERE id = :id' : 'SELECT id, user_id FROM news WHERE id = :id';
        $fetch = $pdo->prepare($selectQuery);
        $fetch->execute(['id' => $id]);
        $existing = $fetch->fetch(PDO::FETCH_ASSOC);

        if (!$existing || (!$isAdmin && (int)$existing['user_id'] !== $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Berita tidak ditemukan atau Anda tidak berhak menghapusnya.']);
            exit;
        }

        if ($hasImageCols) {
            if (!empty($existing['drive_folder_id'])) {
                try {
                    DriveManager::getInstance()->deleteFolder($existing['drive_folder_id']);
                } catch (Throwable $e) {}
            } elseif (!empty($existing['drive_file_id'])) {
                try {
                    DriveManager::getInstance()->deleteFile($existing['drive_file_id']);
                } catch (Throwable $e) {}
            }
        }

        $stmt = $pdo->prepare('DELETE FROM news WHERE id = :id');
        $stmt->execute(['id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Berita berhasil dihapus.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak didukung.']);
} catch (Throwable $exception) {
    error_log('News API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $exception->getMessage() ?: 'Terjadi kesalahan saat memproses berita.']);
}
