<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 1. Ambil Data User
    $stmtUser = $pdo->prepare("SELECT fullName, avatarUrl, prodiId, angkatan, kelas, role FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    if (!empty($userData['avatarUrl'])) {
        $_SESSION['user_avatar'] = $userData['avatarUrl'];
    }

    $prodiId  = $userData['prodiId'] ?? '';
    $angkatan = $userData['angkatan'] ?? '';
    $kelas    = $userData['kelas'] ?? '';

    // 2. Ambil Quote of the Day (dengan Fallback Cepat)
    $quote = ['quote' => 'Tugas itu dikerjakan, bukan direnungkan.', 'author' => 'Overdose Team'];
    try {
        $stmtQuote = $pdo->query("SELECT quote, author FROM quote_list ORDER BY RAND() LIMIT 1");
        if ($stmtQuote && $row = $stmtQuote->fetch(PDO::FETCH_ASSOC)) {
            $quote = $row;
        }
    } catch (Exception $e) {
        // Fallback jika tabel quote_list belum ada
    }

    // Filter Akademik
    $baseFilter = "t.prodiId = :prodiId AND t.angkatan = :angkatan AND t.kelas = :kelas";
    $params = [
        'prodiId'  => $prodiId,
        'angkatan' => $angkatan,
        'kelas'    => $kelas
    ];

    $countDone = 0;
    $countPending = 0;
    $countMissed = 0;
    $myTasksCount = 0;
    $pendingTasks = [];
    $topContributors = [];

    // 3. Ambil Data Tugas & Statistik
    try {
        // Selesai
        $stmtDone = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN taskCompletions tc ON t.taskId = tc.taskId WHERE $baseFilter AND tc.userId = :userId");
        $stmtDone->execute(array_merge($params, ['userId' => $userId]));
        $countDone = (int)$stmtDone->fetchColumn();

        // Pending
        $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $baseFilter AND t.deadline >= NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId)");
        $stmtPending->execute(array_merge($params, ['userId' => $userId]));
        $countPending = (int)$stmtPending->fetchColumn();

        // Terlewat
        $stmtMissed = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $baseFilter AND t.deadline < NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId)");
        $stmtMissed->execute(array_merge($params, ['userId' => $userId]));
        $countMissed = (int)$stmtMissed->fetchColumn();

        // Total Tugas yang Dibuat oleh User saat ini
        $stmtMyCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE createdBy = :userId");
        $stmtMyCount->execute(['userId' => $userId]);
        $myTasksCount = (int)$stmtMyCount->fetchColumn();

        // Daftar Tugas Pending (Short by deadline mepet)
        $stmtTasks = $pdo->prepare("SELECT t.taskId, t.title, t.deadline FROM tasks t WHERE $baseFilter AND t.deadline >= NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId) ORDER BY t.deadline ASC LIMIT 5");
        $stmtTasks->execute(array_merge($params, ['userId' => $userId]));
        $pendingTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        // Top 3 Contributors
        $stmtTop = $pdo->query("SELECT u.userId, u.fullName AS name, u.avatarUrl, u.role, COUNT(t.taskId) as total_tasks 
                                FROM tasks t 
                                JOIN users u ON t.createdBy = u.userId 
                                GROUP BY u.userId, u.fullName, u.avatarUrl, u.role 
                                ORDER BY total_tasks DESC LIMIT 3");
        if ($stmtTop) {
            $topContributors = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Abaikan exception jika tabel belum terisi lengkap
    }

    echo json_encode([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'quote'           => $quote,
            'stats'           => ['done' => $countDone, 'pending' => $countPending, 'missed' => $countMissed],
            'pendingTasks'    => $pendingTasks,
            'topContributors' => $topContributors,
            'currentUser'     => [
                'userId'     => (int)$userId,
                'name'       => $userData['fullName'],
                'avatarUrl'  => $userData['avatarUrl'] ?? '',
                'role'       => $userData['role'] ?? 'Mahasiswa',
                'totalTasks' => $myTasksCount
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}