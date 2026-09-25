<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app/config/config.php';

// Ambil path URL tanpa query string dan tanpa prefix instalasi aplikasi.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/');

if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$path = trim($requestPath, '/');
if (str_starts_with($path, 'index.php')) {
    $path = trim(substr($path, strlen('index.php')), '/');
}

// Support both clean URLs and the legacy query-string entry point.
if ($path === '' && isset($_GET['page'])) {
    $path = trim((string)$_GET['page'], '/');
}

$viewRoutes = [
    '' => 'app/views/dashboard.php',
    'dashboard' => 'app/views/dashboard.php',
    'tasks' => 'app/views/tasks.php',
    'tasks.php' => 'app/views/tasks.php',
    'app/views/tasks.php' => 'app/views/tasks.php',
    'semester' => 'app/views/semester.php',
    'semester/courses' => 'app/views/course.php',
    'course-detail' => 'app/views/course_detail.php',
    'course_detail.php' => 'app/views/course_detail.php',
    'app/views/course_detail.php' => 'app/views/course_detail.php',
    'task-detail' => 'app/views/task_detail.php',
    'task_detail.php' => 'app/views/task_detail.php',
    'app/views/task_detail.php' => 'app/views/task_detail.php',
    'material-detail' => 'app/views/material_detail.php',
    'material_detail.php' => 'app/views/material_detail.php',
    'app/views/material_detail.php' => 'app/views/material_detail.php',
    'task-answers' => 'app/views/task_answers.php',
    'task_answers.php' => 'app/views/task_answers.php',
    'app/views/task_answers.php' => 'app/views/task_answers.php',
    'login' => 'app/views/login.php',
    'configuration' => 'app/views/configurations.php',
    'configurations' => 'app/views/configurations.php',
    'configurations.php' => 'app/views/configurations.php',
    'app/views/configurations.php' => 'app/views/configurations.php',
    'user-management' => 'app/views/user_management.php',
    'users' => 'app/views/user_management.php',
    'blacklist' => 'app/views/blacklist.php',
    'blacklist-email' => 'app/views/blacklist.php',
    'settings' => 'app/views/configurations.php'
];

$apiRoutes = [
    'api/dashboard' => 'app/api/dashboard.php',
    'api/dashboard.php' => 'app/api/dashboard.php',
    'app/api/dashboard.php' => 'app/api/dashboard.php',
    'api/semesters' => 'app/api/semesters.php',
    'api/semesters.php' => 'app/api/semesters.php',
    'app/api/semesters.php' => 'app/api/semesters.php',
    'api/courses' => 'app/api/courses.php',
    'api/courses.php' => 'app/api/courses.php',
    'app/api/courses.php' => 'app/api/courses.php',
    'api/tasks' => 'app/api/tasks.php',
    'api/tasks.php' => 'app/api/tasks.php',
    'app/api/tasks.php' => 'app/api/tasks.php',
    'api/materials' => 'app/api/materials.php',
    'api/materials.php' => 'app/api/materials.php',
    'app/api/materials.php' => 'app/api/materials.php',
    'api/task_answers' => 'app/api/task_answers.php',
    'api/task_answers.php' => 'app/api/task_answers.php',
    'app/api/task_answers.php' => 'app/api/task_answers.php',
    'api/notifications' => 'app/api/notifications.php',
    'api/notifications.php' => 'app/api/notifications.php',
    'app/api/notifications.php' => 'app/api/notifications.php',
    'api/configurations' => 'app/api/configurations.php',
    'api/configurations.php' => 'app/api/configurations.php',
    'app/api/configurations.php' => 'app/api/configurations.php',
    'api/auth/google' => 'app/api/auth/google_redirect.php',
    'api/auth/google/callback' => 'app/api/auth/google_callback.php',
    'api/auth/logout' => 'app/api/auth/logout.php',
    'api/user/onboarding' => 'app/api/user/onboarding.php'
];

$requireRoute = static function (string $relativePath): void {
    $absolutePath = __DIR__ . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Route target tidak ditemukan');
    }
    require $absolutePath;
};

// Kompatibilitas dengan URL lama dari course.js: /tasks?courseId=X.
if ($path === 'tasks') {
    $courseId = (int)($_GET['courseId'] ?? 0);
    if ($courseId <= 0) {
        $requireRoute('app/views/tasks.php');
        exit;
    }
    $_GET['id'] = $courseId;
    $requireRoute('app/views/course_detail.php');
    exit;
}

if (isset($viewRoutes[$path])) {
    $requireRoute($viewRoutes[$path]);
    exit;
}

if (isset($apiRoutes[$path])) {
    $requireRoute($apiRoutes[$path]);
    exit;
}

$isApiRequest = str_starts_with($path, 'api/');
http_response_code(404);
if ($isApiRequest || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan', 'data' => null]);
    exit;
}

http_response_code(404);
?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 — Overdose</title>
            
            <!-- Bootstrap 5 CSS -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <!-- Custom CSS -->
            <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
            <link rel="icon" href="<?= BASE_URL ?>public/assets/img/logo.png" type="image/png">
        </head>
        <body class="login-body">
            <div class="login-container text-center">
                <!-- Logo PNG -->
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="mb-3" style="max-height: 200px; width: auto; object-fit: contain;">
                
                <br>
                <h1 class="fw-bold text-dark mb-2 fs-3">404 — Page Not Found!</h1>
                <p class="text-secondary medium mb-4">Halaman yang diminta tidak ditemukan.</p>

                <a href="<?= BASE_URL ?>" class="btn btn-dark px-4 py-2 rounded-3 fw-semibold text-decoration-none">
                    Balik aja dah
                </a>
            </div>
        </body>
        </html>
    <?php