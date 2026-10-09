<?php
/**
 * ====================================================================================
 * MODULE: Backend API Lupa Absensi (Forgotten Attendances)
 * FILE LOCATION: app/api/forgotten_attendances.php
 * ====================================================================================
 */

require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/attendance_notifications.php';
require_once __DIR__ . '/auth/gatekeeper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---------------------------------------------------------------------------
// Auto daily cleanup: remove all records older than today (server date)
// ---------------------------------------------------------------------------
try {
    $pdo->prepare("DELETE FROM forgotten_attendances WHERE DATE(created_at) < CURDATE()")->execute();
} catch (Throwable $e) {
    error_log('forgotten_attendances cleanup failed: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// Helper: resolve whether a course room is currently "active"
// Active = today is the course day AND current time >= course start time.
// ---------------------------------------------------------------------------
function resolveCourseRoom(PDO $pdo, int $courseId, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT c.courseId, c.courseTitle, c.courseDay, c.startTime, c.endTime, c.semesterId,
               c.courseClass,
               s.majorType, s.studyProgram, s.classGroup, s.batchYear
        FROM courses c
        JOIN semesters s ON c.semesterId = s.semesterId
        WHERE c.courseId = :courseId
          AND s.deletionStatus = 0
          AND s.isActive = 1
        LIMIT 1
    ");
    $stmt->execute(['courseId' => $courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        return null;
    }

    $dayMap = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $todayDay = $dayMap[date('l')] ?? '';

    if ($course['courseDay'] !== $todayDay) {
        $course['isActive'] = false;
        return $course;
    }

    if (!$course['startTime']) {
        $course['isActive'] = false;
        return $course;
    }

    $now = new DateTimeImmutable('now');
    $todayDate = $now->format('Y-m-d');
    $courseStart = new DateTimeImmutable($todayDate . ' ' . $course['startTime']);

    // Active = current time is at or after course start time
    $course['isActive'] = ($now >= $courseStart);
    return $course;
}

// ---------------------------------------------------------------------------
// ACTION: get_room_info
// ---------------------------------------------------------------------------
if ($action === 'get_room_info') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    if ($courseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter course_id tidak valid.']);
        exit;
    }

    $course = resolveCourseRoom($pdo, $courseId, $userId);
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan atau semester tidak aktif.']);
        exit;
    }

    $stmtList = $pdo->prepare("
        SELECT fa.id, fa.user_id, fa.class_name, fa.created_at,
               u.userName, u.avatarUrl, u.roleLevel
        FROM forgotten_attendances fa
        JOIN users u ON fa.user_id = u.userId
        WHERE fa.course_id = :courseId
          AND DATE(fa.created_at) = CURDATE()
        ORDER BY fa.created_at ASC
    ");
    $stmtList->execute(['courseId' => $courseId]);
    $students = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $alreadyRegistered = false;
    foreach ($students as $s) {
        if ((int)$s['user_id'] === $userId) {
            $alreadyRegistered = true;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'course'            => $course,
            'isActive'          => (bool)$course['isActive'],
            'alreadyRegistered' => $alreadyRegistered,
            'students'          => array_map(static function (array $s): array {
                return [
                    'id'        => (int)$s['id'],
                    'userId'    => (int)$s['user_id'],
                    'name'      => $s['userName'],
                    'avatarUrl' => $s['avatarUrl'],
                    'roleLevel' => $s['roleLevel'],
                    'className' => $s['class_name'],
                    'createdAt' => $s['created_at'],
                ];
            }, $students),
        ]
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// ACTION: register (POST)
// ---------------------------------------------------------------------------
if ($action === 'register') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $courseId = (int)($body['course_id'] ?? 0);

    if ($courseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter course_id tidak valid.']);
        exit;
    }

    $course = resolveCourseRoom($pdo, $courseId, $userId);
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan atau semester tidak aktif.']);
        exit;
    }

    if (!$course['isActive']) {
        echo json_encode(['success' => false, 'message' => 'Fitur lupa absen untuk matkul ini sedang tidak aktif!']);
        exit;
    }

    $stmtCheck = $pdo->prepare("
        SELECT id FROM forgotten_attendances
        WHERE course_id = :courseId AND user_id = :userId AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");
    $stmtCheck->execute(['courseId' => $courseId, 'userId' => $userId]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Kamu sudah terdaftar untuk sesi ini.', 'alreadyRegistered' => true]);
        exit;
    }

    $stmtUser = $pdo->prepare("SELECT classGroup FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $userData  = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $className = $userData['classGroup'] ?? '-';

    $stmtInsert = $pdo->prepare("
        INSERT INTO forgotten_attendances (course_id, user_id, class_name, created_at)
        VALUES (:courseId, :userId, :className, NOW())
    ");
    $stmtInsert->execute([
        'courseId'  => $courseId,
        'userId'    => $userId,
        'className' => $className,
    ]);

    echo json_encode(['success' => true, 'message' => 'Berhasil mendaftarkan lupa absensi.']);
    exit;
}

// ---------------------------------------------------------------------------
// ACTION: unregister (POST) — Remove only logged-in user from the list
// ---------------------------------------------------------------------------
if ($action === 'unregister') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $courseId = (int)($body['course_id'] ?? 0);

    if ($courseId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter course_id tidak valid.']);
        exit;
    }

    $stmtDelete = $pdo->prepare("
        DELETE FROM forgotten_attendances
        WHERE course_id = :courseId AND user_id = :userId AND DATE(created_at) = CURDATE()
    ");
    $stmtDelete->execute([
        'courseId' => $courseId,
        'userId'   => $userId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Berhasil membatalkan pendaftaran.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
