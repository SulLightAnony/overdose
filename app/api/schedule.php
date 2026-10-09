<?php
/**
 * ====================================================================================
 * MODULE: Backend API Schedule (Jadwal Perkuliahan)
 * FILE LOCATION: app/api/schedule.php
 * ====================================================================================
 */

require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/attendance_notifications.php';
require_once __DIR__ . '/auth/gatekeeper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if (($_GET['action'] ?? '') === 'attendance_check') {
    try {
        $generated = processAttendanceNotifications($pdo, $userId);
        echo json_encode(['success' => true, 'data' => ['generated' => $generated]]);
    } catch (Throwable $e) {
        error_log('Attendance check failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memeriksa periode absensi.']);
    }
    exit;
}

try {
    // 1. Ambil data akademik user aktif
    $stmtUser = $pdo->prepare("SELECT majorType, studyProgram, classGroup, batchYear, attendanceNotifications FROM users WHERE userId = :userId LIMIT 1");
    $stmtUser->execute(['userId' => $userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $majorType    = $userData['majorType'] ?? '';
    $studyProgram = $userData['studyProgram'] ?? '';
    $classGroup   = $userData['classGroup'] ?? '';
    $batchYear    = (int)($userData['batchYear'] ?? 0);

    // 2. Ambil semester aktif
    $stmtSemester = $pdo->prepare("
        SELECT semesterId, semesterNumber, semesterTitle, backgroundColor, majorType, studyProgram, classGroup, batchYear
        FROM semesters
        WHERE isActive = 1 AND deletionStatus = 0
          AND majorType = :majorType AND studyProgram = :studyProgram AND classGroup = :classGroup AND batchYear = :batchYear
        LIMIT 1
    ");
    $stmtSemester->execute([
        'majorType'    => $majorType,
        'studyProgram' => $studyProgram,
        'classGroup'   => $classGroup,
        'batchYear'    => $batchYear
    ]);
    $activeSemester = $stmtSemester->fetch(PDO::FETCH_ASSOC);

    if (!$activeSemester) {
        echo json_encode([
            'success' => true,
            'data' => [
                'semester' => null,
                'scheduleByDay' => [],
                'todayDay' => date('l'),
                'tomorrowDay' => date('l', strtotime('+1 day')),
                'serverNow' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)
            ]
        ]);
        exit;
    }

    // 3. Ambil daftar mata kuliah untuk semester aktif
    $stmtCourses = $pdo->prepare("
        SELECT courseId, semesterId, semesterNumber, courseCode, courseTitle, courseType,
               courseClass, courseDay, startTime, endTime, courseDescription, lecturerName, lecturerEmail, lecturerPhone, backgroundColor
        FROM courses
        WHERE semesterId = :semesterId
        ORDER BY FIELD(courseDay, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), startTime ASC
    ");
    $stmtCourses->execute(['semesterId' => $activeSemester['semesterId']]);
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

    // 4. Hitung Hari Ini & Besok dalam Bahasa Indonesia
    $dayMap = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu'
    ];
    $currentEng = date('l');
    $tomorrowEng = date('l', strtotime('+1 day'));
    $todayDay = $dayMap[$currentEng] ?? 'Senin';
    $tomorrowDay = $dayMap[$tomorrowEng] ?? 'Selasa';

    // 5. Kelompokkan berdasarkan hari
    $daysOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $scheduleByDay = [];

    foreach ($daysOrder as $day) {
        $scheduleByDay[$day] = [];
    }

    foreach ($courses as &$c) {
        $c['isAttendanceActive'] = isWithinAttendanceWindow(new DateTimeImmutable('now'), $c['startTime'], $c['endTime']);
        $day = $c['courseDay'] ?? 'Senin';
        if (!isset($scheduleByDay[$day])) {
            $scheduleByDay[$day] = [];
        }
        $scheduleByDay[$day][] = $c;
    }
    unset($c);

    echo json_encode([
        'success' => true,
        'data' => [
            'semester'      => $activeSemester,
            'scheduleByDay' => $scheduleByDay,
            'todayDay'      => $todayDay,
            'tomorrowDay'   => $tomorrowDay,
            'serverNow'     => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}
