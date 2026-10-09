<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/push_sender.php';

function isWithinAttendanceWindow(DateTimeImmutable $now, ?string $startTime, ?string $endTime): bool
{
    if (!$startTime || !$endTime) {
        return false;
    }

    $date = $now->format('Y-m-d');
    $start = new DateTimeImmutable($date . ' ' . $startTime);
    $end = new DateTimeImmutable($date . ' ' . $endTime);
    return $now >= $start->modify('-30 minutes') && $now < $end;
}

function processAttendanceNotifications(PDO $pdo, ?int $onlyUserId = null): int
{
    $now = new DateTimeImmutable('now');
    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $today = $dayNames[(int)$now->format('N')];

    $userSql = 'SELECT userId, majorType, studyProgram, classGroup, batchYear, attendanceNotifications FROM users WHERE userStatus = \'active\'';
    $userParams = [];
    if ($onlyUserId !== null) {
        $userSql .= ' AND userId = :userId';
        $userParams['userId'] = $onlyUserId;
    }
    $stmtUsers = $pdo->prepare($userSql);
    $stmtUsers->execute($userParams);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    $generated = 0;

    foreach ($users as $user) {
        $stmtSemester = $pdo->prepare('SELECT semesterId FROM semesters
                        WHERE deletionStatus = 0 AND isActive = 1
                            AND majorType = :major AND studyProgram = :program
              AND classGroup = :classGroup AND batchYear = :batchYear
                        ORDER BY semesterNumber DESC, semesterId DESC LIMIT 1');
        $stmtSemester->execute([
            'major' => $user['majorType'],
            'program' => $user['studyProgram'],
            'classGroup' => $user['classGroup'],
            'batchYear' => $user['batchYear']
        ]);
        $semesterId = $stmtSemester->fetchColumn();
        if (!$semesterId) {
            continue;
        }

        $stmtCourses = $pdo->prepare('SELECT courseId, courseTitle, courseDay, startTime, endTime
            FROM courses WHERE semesterId = :semesterId AND courseDay = :courseDay');
        $stmtCourses->execute(['semesterId' => $semesterId, 'courseDay' => $today]);

        foreach ($stmtCourses->fetchAll(PDO::FETCH_ASSOC) as $course) {
            if (!isWithinAttendanceWindow($now, $course['startTime'], $course['endTime'])) {
                continue;
            }

            $targetUrl = BASE_URL . 'course-detail?id=' . (int)$course['courseId'];
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO notifications
                (userId, notificationTitle, notificationMessage, notificationType, targetUrl, relatedCourseId, notificationDate)
                VALUES (:userId, :title, :message, 'attendance', :targetUrl, :courseId, :notificationDate)");
            $stmtInsert->execute([
                'userId' => $user['userId'],
                'title' => 'Waktu Absensi Aktif',
                'message' => 'Periode absensi untuk ' . $course['courseTitle'] . ' sedang aktif.',
                'targetUrl' => $targetUrl,
                'courseId' => $course['courseId'],
                'notificationDate' => $now->format('Y-m-d')
            ]);

            if ($stmtInsert->rowCount() !== 1) {
                continue;
            }

            $generated++;
            if ((int)$user['attendanceNotifications'] === 1) {
                sendWebPushToUser($pdo, (int)$user['userId'], [
                    'title' => 'Waktu Absensi Aktif',
                    'body' => 'Periode absensi untuk ' . $course['courseTitle'] . ' sedang aktif.',
                    'url' => $targetUrl,
                    'type' => 'attendance'
                ]);
            }
        }
    }

    return $generated;
}
