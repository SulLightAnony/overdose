<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app/config/config.php';

// Ambil path dari URL
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = parse_url(BASE_URL, PHP_URL_PATH);

// Bersihkan base path dari request URI
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

$path = trim(parse_url($path, PHP_URL_PATH), '/');

// Routing sederhana
switch ($path) {
    case '':
    case 'dashboard':
        require 'app/views/dashboard.php';
        break;

    case 'semester':
        require 'app/views/semester.php';
        break;

    case 'login':
        require 'app/views/login.php';
        break;

    case 'settings':
        require 'app/views/settings.php';
        break;

    // Routing API Endpoint
    case 'api/auth/google':
        require 'app/api/auth/google_redirect.php';
        break;

    case 'api/auth/google/callback':
        require 'app/api/auth/google_callback.php';
        break;

    case 'api/auth/logout':
        require 'app/api/auth/logout.php';
        break;

    case 'api/user/onboarding':
        require 'app/api/user/onboarding.php';
        break;

    case 'api/dashboard':
    case 'api/dashboard.php':
    case 'app/api/dashboard.php':
        require 'app/api/dashboard.php';
        break;

    case 'api/semesters':
    case 'api/semesters.php':
    case 'app/api/semesters.php':
        require 'app/api/semesters.php';
        break;

    // Routing untuk Halaman Mata Kuliah
    case 'semester/courses':
        require 'app/views/course.php';
        break;

    // Routing untuk API Mata Kuliah
    case 'api/courses':
    case 'api/courses.php':
    case 'app/api/courses.php':
        require 'app/api/courses.php';
        break;

    default:
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
                <p class="text-secondary medium mb-4">Nyari apa banh? Ga ada yang begitu di sini😛</p>

                <a href="<?= BASE_URL ?>" class="btn btn-dark px-4 py-2 rounded-3 fw-semibold text-decoration-none">
                    Balik aja dah
                </a>
            </div>
        </body>
        </html>
        <?php
        break;
}