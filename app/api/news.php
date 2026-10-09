<?php
require_once __DIR__ . '/../config/config.php';
init_secure_session();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role_level'] ?? 'Keroco';
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');
$isAdmin = in_array($userRole, ['Sepuh', 'Primordial'], true);

try {
    if ($method === 'GET' && $action === 'list') {
        $stmt = $pdo->query('SELECT n.id, n.user_id, n.title, n.content, n.created_at, u.userName
            FROM news n LEFT JOIN users u ON u.userId = n.user_id ORDER BY n.created_at DESC');
        echo json_encode(['success' => true, 'data' => ['news' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || $content === '' || mb_strlen($title) > 200) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Judul dan isi berita wajib diisi.']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO news (user_id, title, content) VALUES (:userId, :title, :content)');
        $stmt->execute(['userId' => $userId, 'title' => $title, 'content' => $content]);
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
        $stmt = $pdo->prepare('UPDATE news SET title = :title, content = :content WHERE id = :id AND (user_id = :userId OR :isAdmin = 1)');
        $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id, 'userId' => $userId, 'isAdmin' => $isAdmin ? 1 : 0]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Berita tidak ditemukan atau tidak dapat diedit.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Berita diperbarui.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM news WHERE id = :id AND (user_id = :userId OR :isAdmin = 1)');
        $stmt->execute(['id' => $id, 'userId' => $userId, 'isAdmin' => $isAdmin ? 1 : 0]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Berita tidak ditemukan atau tidak dapat dihapus.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Berita dihapus.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak didukung.']);
} catch (Throwable $exception) {
    error_log('News API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses berita.']);
}
