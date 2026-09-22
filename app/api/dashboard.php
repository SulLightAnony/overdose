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
    // Ambil data profil & akademik user dari database
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

    $prodiId  = $userData['prodiId'];
    $angkatan = $userData['angkatan'];
    $kelas    = $userData['kelas'];

    // 1. Quote of the Day
    $quote = ['quote' => 'Tetap semangat menjalani perkuliahan!', 'author' => 'Overdose Team'];
    $stmtQuote = $pdo->query("SELECT quote, author FROM quote_list ORDER BY RAND() LIMIT 1");
    if ($stmtQuote && $row = $stmtQuote->fetch(PDO::FETCH_ASSOC)) {
        $quote = $row;
    }

    // Filter basis tugas berdasarkan prodiId, angkatan, dan kelas user
    $baseFilter = "t.prodiId = :prodiId AND t.angkatan = :angkatan AND t.kelas = :kelas";
    $params = [
        'prodiId'  => $prodiId,
        'angkatan' => $angkatan,
        'kelas'    => $kelas
    ];

    // 2. Statistik: Selesai
    $stmtDone = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN taskCompletions tc ON t.taskId = tc.taskId WHERE $baseFilter AND tc.userId = :userId");
    $stmtDone->execute(array_merge($params, ['userId' => $userId]));
    $countDone = (int)$stmtDone->fetchColumn();

    // 3. Statistik: Pending
    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $baseFilter AND t.deadline >= NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId)");
    $stmtPending->execute(array_merge($params, ['userId' => $userId]));
    $countPending = (int)$stmtPending->fetchColumn();

    // 4. Statistik: Terlewat
    $stmtMissed = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $baseFilter AND t.deadline < NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId)");
    $stmtMissed->execute(array_merge($params, ['userId' => $userId]));
    $countMissed = (int)$stmtMissed->fetchColumn();

    // 5. Daftar Tugas Pending (diurutkan berdasarkan deadline terdekat)
    $stmtTasks = $pdo->prepare("SELECT t.taskId, t.title, t.deadline FROM tasks t WHERE $baseFilter AND t.deadline >= NOW() AND t.taskId NOT IN (SELECT taskId FROM taskCompletions WHERE userId = :userId) ORDER BY t.deadline ASC");
    $stmtTasks->execute(array_merge($params, ['userId' => $userId]));
    $pendingTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    // 6. Top 3 Contributors
    $topContributors = [];
    $stmtTop = $pdo->query("SELECT u.fullName AS name, u.avatarUrl, u.role, COUNT(t.taskId) as total_tasks 
                            FROM tasks t 
                            JOIN users u ON t.createdBy = u.userId 
                            GROUP BY t.createdBy, u.fullName, u.avatarUrl, u.role 
                            ORDER BY total_tasks DESC LIMIT 3");
    if ($stmtTop) {
        $topContributors = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'quote'           => $quote,
            'stats'           => ['done' => $countDone, 'pending' => $countPending, 'missed' => $countMissed],
            'pendingTasks'    => $pendingTasks,
            'topContributors' => $topContributors
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}