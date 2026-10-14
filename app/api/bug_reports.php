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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?: [];
$input = array_merge($_GET, $_POST, $jsonInput);
$action = $input['action'] ?? '';
$isReviewer = in_array($userRole, ['Sepuh', 'Primordial'], true);

try {
    // -------------------------------------------------------------------------
    // Cek Sisa Waktu Cooldown Pengiriman Laporan:
    // Primordial: Tanpa batas waktu (0 detik)
    // Sepuh: 1 Menit (60 detik)
    // Keroco / Lainnya: 1 Jam (3600 detik)
    // -------------------------------------------------------------------------
    $cooldownLimit = match ($userRole) {
        'Primordial' => 0,
        'Sepuh' => 60,
        default => 3600
    };

    $cooldownSeconds = 0;
    if ($cooldownLimit > 0) {
        $cooldownStmt = $pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed_seconds
            FROM bug_reports
            WHERE user_id = :userId
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $cooldownStmt->execute(['userId' => $userId]);
        $elapsed = $cooldownStmt->fetchColumn();
        if ($elapsed !== false && (int)$elapsed < $cooldownLimit) {
            $cooldownSeconds = $cooldownLimit - (int)$elapsed;
        }
    }

    if ($action === 'cooldown_status') {
        echo json_encode([
            'success' => true,
            'cooldownSeconds' => $cooldownSeconds,
            'cooldownLimit' => $cooldownLimit
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Aksi 1: Daftar Laporan (Khusus Reviewer: Sepuh & Primordial)
    // -------------------------------------------------------------------------
    if ($method === 'GET' && $action === 'list') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $search = trim((string)($input['search'] ?? ''));
        $statusFilter = trim((string)($input['status'] ?? 'all'));
        $sort = trim((string)($input['sort'] ?? 'newest'));
        $page = max(1, (int)($input['page'] ?? 1));
        $limit = max(1, min(100, (int)($input['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $whereClauses = ['1=1'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(r.description LIKE :searchDesc OR u.userName LIKE :searchUser)';
            $params['searchDesc'] = "%{$search}%";
            $params['searchUser'] = "%{$search}%";
        }

        if ($statusFilter === 'in_progress') {
            $whereClauses[] = "r.status IN ('in_progress', 'open')";
        } elseif ($statusFilter === 'resolved') {
            $whereClauses[] = "r.status IN ('resolved', 'closed')";
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Hitung total laporan sesuai filter
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bug_reports r 
            LEFT JOIN users u ON u.userId = r.user_id 
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $limit);

        // Aturan Pengurutan: Item berstatus Diproses (in_progress/open) SELALU di atas,
        // setelah semua item Diproses selesai, barulah item Selesai (resolved/closed) ditampilkan.
        // Filter sort (newest/oldest) berlaku di dalam masing-masing grup status tersebut.
        $dateOrder = ($sort === 'oldest') ? 'r.created_at ASC' : 'r.created_at DESC';
        $orderBy = "CASE WHEN r.status IN ('in_progress', 'open') THEN 0 ELSE 1 END ASC, {$dateOrder}";

        $stmt = $pdo->prepare("
            SELECT r.id, r.user_id, r.description, r.status, r.created_at, u.userName, u.avatarUrl, u.roleLevel
            FROM bug_reports r 
            LEFT JOIN users u ON u.userId = r.user_id 
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ringkasan status untuk tab counter (khusus status bukan selesai / diproses saja)
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('in_progress', 'open') THEN 1 ELSE 0 END) as totalInProgress,
                SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as totalResolved
            FROM bug_reports
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'data' => [
                'reports' => $reports,
                'stats' => [
                    'total' => (int)($stats['total'] ?? 0),
                    'totalInProgress' => (int)($stats['totalInProgress'] ?? 0),
                    'totalResolved' => (int)($stats['totalResolved'] ?? 0)
                ],
                'pagination' => [
                    'totalItems' => $totalItems,
                    'totalPages' => $totalPages,
                    'currentPage' => $page,
                    'limit' => $limit
                ]
            ]
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Aksi 2: Kirim Laporan Baru (Dengan Cooldown Berdasarkan Role)
    // -------------------------------------------------------------------------
    if ($method === 'POST' && $action === 'create') {
        if ($cooldownLimit > 0 && $cooldownSeconds > 0) {
            if ($cooldownLimit <= 60) {
                $msg = "Sebagai Sepuh, Anda hanya dapat mengirim laporan sekali setiap 1 menit. Silakan tunggu {$cooldownSeconds} detik lagi.";
            } else {
                $remainingMinutes = ceil($cooldownSeconds / 60);
                $msg = "Anda hanya dapat mengirim laporan sekali setiap 1 jam untuk mencegah spam. Silakan tunggu {$remainingMinutes} menit lagi.";
            }
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => $msg,
                'cooldownSeconds' => $cooldownSeconds
            ]);
            exit;
        }

        $description = trim((string)($input['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 10000) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Deskripsi wajib diisi dan maksimal 10.000 karakter.']);
            exit;
        }

        // Laporan baru otomatis berstatus 'in_progress' (Diproses)
        $stmt = $pdo->prepare("INSERT INTO bug_reports (user_id, description, status) VALUES (:userId, :description, 'in_progress')");
        $stmt->execute(['userId' => $userId, 'description' => $description]);

        echo json_encode([
            'success' => true,
            'message' => 'Laporan berhasil dikirim. Terima kasih!',
            'cooldownSeconds' => $cooldownLimit
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Aksi 3: Perbarui Status Laporan (Selesai / Diproses)
    // -------------------------------------------------------------------------
    if ($method === 'POST' && $action === 'update_status') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $reportId = (int)($input['id'] ?? 0);
        $status = (string)($input['status'] ?? '');

        // Normalisasi status ke in_progress atau resolved
        if ($status === 'open') {
            $status = 'in_progress';
        } elseif ($status === 'closed') {
            $status = 'resolved';
        }

        if ($reportId <= 0 || !in_array($status, ['in_progress', 'resolved'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Data status laporan tidak valid.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE bug_reports SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $reportId]);

        echo json_encode([
            'success' => true,
            'message' => ($status === 'resolved') ? 'Laporan ditandai selesai.' : 'Laporan dikembalikan ke status diproses.'
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // Aksi 4: Hapus Laporan
    // -------------------------------------------------------------------------
    if ($method === 'DELETE') {
        if (!$isReviewer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $reportId = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM bug_reports WHERE id = :id');
        $stmt->execute(['id' => $reportId]);

        echo json_encode(['success' => true, 'message' => 'Laporan berhasil dihapus.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak didukung.']);
} catch (Throwable $exception) {
    error_log('Bug reports API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses laporan.']);
}
