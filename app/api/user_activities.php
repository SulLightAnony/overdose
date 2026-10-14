<?php
/**
 * MODULE: User Activities Unified API
 * FILE LOCATION: app/api/user_activities.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/gatekeeper.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserRole = $_SESSION['role_level'] ?? 'Keroco';

if (!in_array($currentUserRole, ['Primordial', 'Sepuh'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Riwayat aktivitas pengguna hanya dapat diakses oleh Primordial dan Sepuh.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?: [];
$input = array_merge($_GET, $_POST, $jsonInput);

$targetUserId = (int)($input['userId'] ?? 0);
if ($targetUserId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Parameter ID pengguna tidak valid.']);
    exit;
}

// Verify target user exists
$userStmt = $pdo->prepare("SELECT userId, userName, emailAddress, avatarUrl, roleLevel FROM users WHERE userId = :id LIMIT 1");
$userStmt->execute(['id' => $targetUserId]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
    exit;
}

$typeFilter = trim((string)($input['type'] ?? 'all'));
$search = trim((string)($input['search'] ?? ''));
$sort = trim((string)($input['sort'] ?? 'newest'));
$page = max(1, (int)($input['page'] ?? 1));
$limit = max(1, min(100, (int)($input['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

try {
    // 1. Calculate Activity Counters per Type
    $countsStmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM tasks WHERE createdByUserId = :uid1) AS totalTasks,
            (SELECT COUNT(*) FROM learning_material WHERE uploadedByUserId = :uid2) AS totalMaterials,
            (SELECT COUNT(*) FROM task_shared_answers WHERE userId = :uid3) AS totalAnswers,
            (SELECT COUNT(*) FROM task_comments WHERE userId = :uid4) AS totalComments,
            (SELECT COUNT(*) FROM news WHERE user_id = :uid5) AS totalNews,
            (SELECT COUNT(*) FROM global_news WHERE user_id = :uid6) AS totalGlobalNews,
            (SELECT COUNT(*) FROM global_chats WHERE user_id = :uid7) AS totalChats,
            (SELECT COUNT(*) FROM bug_reports WHERE user_id = :uid8) AS totalBugReports
    ");
    for ($i = 1; $i <= 8; $i++) {
        $countsStmt->bindValue(":uid$i", $targetUserId, PDO::PARAM_INT);
    }
    $countsStmt->execute();
    $statCounts = $countsStmt->fetch(PDO::FETCH_ASSOC);

    $totalActivities = 
        (int)$statCounts['totalTasks'] +
        (int)$statCounts['totalMaterials'] +
        (int)$statCounts['totalAnswers'] +
        (int)$statCounts['totalComments'] +
        (int)$statCounts['totalNews'] +
        (int)$statCounts['totalGlobalNews'] +
        (int)$statCounts['totalChats'] +
        (int)$statCounts['totalBugReports'];

    $statCounts['totalActivities'] = $totalActivities;

    // 2. Build Unified Query
    $unionSql = "
        SELECT 
            'task' COLLATE utf8mb4_unicode_ci AS activityType,
            taskId AS itemId,
            taskTitle COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(taskDescription AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(createdAt AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('task-detail?id=', taskId) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM tasks 
        WHERE createdByUserId = :uid1

        UNION ALL

        SELECT 
            'material' COLLATE utf8mb4_unicode_ci AS activityType,
            materialId AS itemId,
            materialTitle COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(materialDescription AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(createdAt AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('material-detail?id=', materialId) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM learning_material 
        WHERE uploadedByUserId = :uid2

        UNION ALL

        SELECT 
            'answer' COLLATE utf8mb4_unicode_ci AS activityType,
            answerId AS itemId,
            answerTitle COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(answerNotes AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(createdAt AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('task-detail?id=', taskId) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM task_shared_answers 
        WHERE userId = :uid3

        UNION ALL

        SELECT 
            'comment' COLLATE utf8mb4_unicode_ci AS activityType,
            commentId AS itemId,
            CONCAT('Komentar pada Tugas #', taskId) COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(commentContent AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(createdAt AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('task-detail?id=', taskId) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM task_comments 
        WHERE userId = :uid4

        UNION ALL

        SELECT 
            'news' COLLATE utf8mb4_unicode_ci AS activityType,
            id AS itemId,
            title COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(content AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(created_at AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('news#news-', id) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM news 
        WHERE user_id = :uid5

        UNION ALL

        SELECT 
            'global_news' COLLATE utf8mb4_unicode_ci AS activityType,
            id AS itemId,
            title COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(content AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(created_at AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            CONCAT('global#news-', id) COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM global_news 
        WHERE user_id = :uid6

        UNION ALL

        SELECT 
            'chat' COLLATE utf8mb4_unicode_ci AS activityType,
            id AS itemId,
            'Pesan Obrolan Global' COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(message AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(created_at AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            'global' COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM global_chats 
        WHERE user_id = :uid7

        UNION ALL

        SELECT 
            'bug_report' COLLATE utf8mb4_unicode_ci AS activityType,
            id AS itemId,
            'Laporan Kendala' COLLATE utf8mb4_unicode_ci AS itemTitle,
            CAST(description AS CHAR) COLLATE utf8mb4_unicode_ci AS itemSnippet,
            CAST(created_at AS CHAR) COLLATE utf8mb4_unicode_ci AS activityDate,
            'bug-report' COLLATE utf8mb4_unicode_ci AS targetUrl
        FROM bug_reports 
        WHERE user_id = :uid8
    ";

    $whereOuter = ['1=1'];
    $outerParams = [];

    if ($typeFilter !== '' && $typeFilter !== 'all') {
        $whereOuter[] = 'sub.activityType = :typeFilter';
        $outerParams['typeFilter'] = $typeFilter;
    }

    if ($search !== '') {
        $whereOuter[] = '(sub.itemTitle LIKE :searchTitle OR sub.itemSnippet LIKE :searchSnippet)';
        $outerParams['searchTitle'] = "%{$search}%";
        $outerParams['searchSnippet'] = "%{$search}%";
    }

    $whereOuterSql = implode(' AND ', $whereOuter);

    // Count filtered activities
    $countSql = "SELECT COUNT(*) FROM ({$unionSql}) sub WHERE {$whereOuterSql}";
    $countStmt = $pdo->prepare($countSql);
    for ($i = 1; $i <= 8; $i++) {
        $countStmt->bindValue(":uid$i", $targetUserId, PDO::PARAM_INT);
    }
    foreach ($outerParams as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $totalFiltered = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalFiltered / $limit);

    $orderBy = ($sort === 'oldest') ? 'sub.activityDate ASC' : 'sub.activityDate DESC';

    $dataSql = "
        SELECT sub.* 
        FROM ({$unionSql}) sub 
        WHERE {$whereOuterSql}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $pdo->prepare($dataSql);
    for ($i = 1; $i <= 8; $i++) {
        $dataStmt->bindValue(":uid$i", $targetUserId, PDO::PARAM_INT);
    }
    foreach ($outerParams as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $activities = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $targetUser,
            'stats' => $statCounts,
            'activities' => $activities,
            'pagination' => [
                'totalItems' => $totalFiltered,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'limit' => $limit
            ]
        ]
    ]);
    exit;

} catch (Throwable $e) {
    error_log('User Activities API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memuat riwayat aktivitas pengguna.']);
    exit;
}
