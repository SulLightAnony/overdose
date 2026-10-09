<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/attendance_notifications.php';

try {
    $generated = processAttendanceNotifications($pdo);
    fwrite(STDOUT, sprintf("Attendance notifications generated: %d\n", $generated));
} catch (Throwable $exception) {
    error_log('Attendance notification scheduler failed: ' . $exception->getMessage());
    fwrite(STDERR, "Attendance notification scheduler failed. Check the PHP error log.\n");
    exit(1);
}
