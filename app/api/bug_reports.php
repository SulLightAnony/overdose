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
$isReviewer = in_array($userRole, ['Sepuh', 'Primordial'], true);

try {
    if ($method === 'GET' && $action === 'list') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        $stmt = $pdo->query('SELECT r.id, r.user_id, r.description, r.status, r.created_at, u.userName
            FROM bug_reports r LEFT JOIN users u ON u.userId = r.user_id ORDER BY r.created_at DESC');
        echo json_encode(['success' => true, 'data' => ['reports' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $description = trim((string)($input['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 10000) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Deskripsi wajib diisi dan maksimal 10.000 karakter.']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO bug_reports (user_id, description, status) VALUES (:userId, :description, \'open\')');
        $stmt->execute(['userId' => $userId, 'description' => $description]);
        echo json_encode(['success' => true, 'message' => 'Laporan berhasil dikirim.']);
        exit;
    }

    if ($method === 'POST' && $action === 'update_status') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        $reportId = (int)($input['id'] ?? 0);
        $status = (string)($input['status'] ?? '');
        if ($reportId <= 0 || !in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Data status laporan tidak valid.']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE bug_reports SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $reportId]);
        echo json_encode(['success' => true, 'message' => 'Status laporan diperbarui.']);
        exit;
    }

    if ($method === 'DELETE') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        $reportId = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM bug_reports WHERE id = :id');
        $stmt->execute(['id' => $reportId]);
        echo json_encode(['success' => true, 'message' => 'Laporan dihapus.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak didukung.']);
} catch (Throwable $exception) {
    error_log('Bug reports API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses laporan.']);
}
