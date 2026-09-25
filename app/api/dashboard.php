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
    // 1. Ambil data profil & akademik user sesuai skema app_overdose.sql
    $stmtUser = $pdo->prepare("SELECT userName, avatarUrl, majorType, studyProgram, classGroup, batchYear, roleLevel FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    if (!empty($userData['avatarUrl'])) {
        $_SESSION['user_avatar'] = $userData['avatarUrl'];
    }

    $majorType    = $userData['majorType'] ?? '';
    $studyProgram = $userData['studyProgram'] ?? '';
    $classGroup   = $userData['classGroup'] ?? '';
    $batchYear    = $userData['batchYear'] ?? '';

    // 2. Ambil Quote of the Day dari tabel quote_list (Kolom: id, quote, author)
    $quote = ['quote' => 'Tugas itu dikerjakan, bukan direnungkan.', 'author' => 'Overdose Team'];
    try {
        $stmtQuote = $pdo->query("SELECT quote, author FROM quote_list ORDER BY RAND() LIMIT 1");
        if ($stmtQuote && $row = $stmtQuote->fetch(PDO::FETCH_ASSOC)) {
            $quote = $row;
        }
    } catch (Exception $e) {
        // Abaikan jika tabel quote_list bermasalah
    }

    // Filter akademik berdasarkan relasi tabel courses & tasks
    $baseFilter = "c.majorType = :majorType AND c.studyProgram = :studyProgram AND c.classGroup = :classGroup AND c.batchYear = :batchYear";
    $params = [
        'majorType'    => $majorType,
        'studyProgram' => $studyProgram,
        'classGroup'   => $classGroup,
        'batchYear'    => $batchYear
    ];

    $countDone = 0;
    $countPending = 0;
    $countMissed = 0;
    $myTasksCount = 0;
    $pendingTasks = [];
    $topContributors = [];

    try {
        // Statistik: Selesai
        $stmtDone = $pdo->prepare("SELECT COUNT(DISTINCT t.taskId) FROM tasks t JOIN courses c ON t.courseId = c.courseId JOIN task_completions tc ON t.taskId = tc.taskId WHERE $baseFilter AND tc.userId = :userId");
        $stmtDone->execute(array_merge($params, ['userId' => $userId]));
        $countDone = (int)$stmtDone->fetchColumn();

        // Statistik: Pending
        $stmtPending = $pdo->prepare("SELECT COUNT(DISTINCT t.taskId) FROM tasks t JOIN courses c ON t.courseId = c.courseId WHERE $baseFilter AND t.dueDate >= NOW() AND t.taskId NOT IN (SELECT taskId FROM task_completions WHERE userId = :userId)");
        $stmtPending->execute(array_merge($params, ['userId' => $userId]));
        $countPending = (int)$stmtPending->fetchColumn();

        // Statistik: Terlewat
        $stmtMissed = $pdo->prepare("SELECT COUNT(DISTINCT t.taskId) FROM tasks t JOIN courses c ON t.courseId = c.courseId WHERE $baseFilter AND t.dueDate < NOW() AND t.taskId NOT IN (SELECT taskId FROM task_completions WHERE userId = :userId)");
        $stmtMissed->execute(array_merge($params, ['userId' => $userId]));
        $countMissed = (int)$stmtMissed->fetchColumn();

        // Total Tugas yang Dibuat oleh User saat ini
        $stmtMyCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE createdByUserId = :userId");
        $stmtMyCount->execute(['userId' => $userId]);
        $myTasksCount = (int)$stmtMyCount->fetchColumn();

        // Daftar Tugas Pending (diurutkan berdasarkan dueDate terdekat)
        $stmtTasks = $pdo->prepare("SELECT t.taskId, t.taskTitle AS title, t.dueDate AS deadline FROM tasks t JOIN courses c ON t.courseId = c.courseId WHERE $baseFilter AND t.dueDate >= NOW() AND t.taskId NOT IN (SELECT taskId FROM task_completions WHERE userId = :userId) ORDER BY t.dueDate ASC LIMIT 5");
        $stmtTasks->execute(array_merge($params, ['userId' => $userId]));
        $pendingTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        // Top 3 Contributors
        $stmtTop = $pdo->query("SELECT u.userId, u.userName AS name, u.avatarUrl, u.roleLevel AS role, COUNT(t.taskId) as total_tasks 
                                FROM tasks t 
                                JOIN users u ON t.createdByUserId = u.userId 
                                GROUP BY u.userId, u.userName, u.avatarUrl, u.roleLevel 
                                ORDER BY total_tasks DESC LIMIT 3");
        if ($stmtTop) {
            $topContributors = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Tangkap error kueri jika data relasi kosong
    }

    // 3. Ambil Jadwal Hari Ini & Besok (Difilter khusus Semester Aktif / Fallback ke Semester Terbaru)
    $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $todayIndex = (int)date('N');
    $tomorrowIndex = $todayIndex + 1 > 7 ? 1 : $todayIndex + 1;
    
    $todayName = $days[$todayIndex];
    $tomorrowName = $days[$tomorrowIndex];

    $todayCourses = [];
    $tomorrowCourses = [];
    try {
        $stmtSchedule = $pdo->prepare("
            SELECT c.*
            FROM courses c
            JOIN semesters s ON c.semesterId = s.semesterId
            WHERE s.deletionStatus = 0
                AND s.majorType = :majorType
                AND s.studyProgram = :studyProgram
                AND s.classGroup = :classGroup
                AND s.batchYear = :batchYear
                                AND s.semesterId = COALESCE(
                    (
                                                SELECT semesterId
                        FROM semesters
                        WHERE deletionStatus = 0
                                                    AND majorType = :activeMajorType
                                                    AND studyProgram = :activeStudyProgram
                                                    AND classGroup = :activeClassGroup
                                                    AND batchYear = :activeBatchYear
                          AND isActive = 1
                        LIMIT 1
                    ),
                    (
                                                SELECT semesterId
                        FROM semesters
                        WHERE deletionStatus = 0
                                                    AND majorType = :fallbackMajorType
                                                    AND studyProgram = :fallbackStudyProgram
                                                    AND classGroup = :fallbackClassGroup
                                                    AND batchYear = :fallbackBatchYear
                        ORDER BY semesterNumber DESC, semesterId DESC
                        LIMIT 1
                    )
                )
                AND c.courseDay IN (:today, :tomorrow)
            ORDER BY c.startTime ASC
        ");
        $stmtSchedule->execute([
            'majorType'    => $majorType,
            'studyProgram' => $studyProgram,
            'classGroup'   => $classGroup,
            'batchYear'    => $batchYear,
            'activeMajorType' => $majorType,
            'activeStudyProgram' => $studyProgram,
            'activeClassGroup' => $classGroup,
            'activeBatchYear' => $batchYear,
            'fallbackMajorType' => $majorType,
            'fallbackStudyProgram' => $studyProgram,
            'fallbackClassGroup' => $classGroup,
            'fallbackBatchYear' => $batchYear,
            'today'        => $todayName,
            'tomorrow'     => $tomorrowName
        ]);

        foreach ($stmtSchedule->fetchAll(PDO::FETCH_ASSOC) as $course) {
            if ($course['courseDay'] === $todayName) {
                $todayCourses[] = $course;
            } elseif ($course['courseDay'] === $tomorrowName) {
                $tomorrowCourses[] = $course;
            }
        }
    } catch (Exception $e) {
        // Keep the dashboard payload available when schedule data is unavailable.
    }

    // 4. Output JSON Tunggal dan Lengkap
    echo json_encode([
        'success' => true,
        'message' => 'OK',
        'data'    => [
            'quote'           => $quote,
            'stats'           => [
                'done'    => $countDone, 
                'pending' => $countPending, 
                'missed'  => $countMissed
            ],
            'pendingTasks'    => $pendingTasks,
            'topContributors' => $topContributors,
            'currentUser'     => [
                'userId'     => (int)$userId,
                'name'       => $userData['userName'],
                'avatarUrl'  => $userData['avatarUrl'] ?? '',
                'role'       => $userData['roleLevel'] ?? 'Keroco',
                'totalTasks' => $myTasksCount
            ],
            'schedule'        => [
                'todayName'       => $todayName,
                'tomorrowName'    => $tomorrowName,
                'todayCourses'    => $todayCourses,
                'tomorrowCourses' => $tomorrowCourses
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}