<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/app/config/config.php';

$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';

// Normalisasi jika request menyertakan prefix 'app/' dari BASE_URL
if (str_starts_with($path, 'app/')) {
    $path = substr($path, 4);
}

switch ($path) {
    case '':
    case 'login':
        require 'app/views/login.php';
        break;
        
    case 'dashboard':
        require 'app/views/dashboard.php';
        break;
        
    case 'semester':
        require 'app/views/semester.php';
        break;
        
    case 'task/detail':
        require 'app/views/task_detail.php';
        break;
        
    case 'settings':
        require 'app/views/settings.php';
        break;

    // Routing Logout Sementara (Hanya aktif saat mode offline/pengembangan)
    case 'logout':
        if (isset($online) && !$online) {
            require 'app/api/auth/logout.php';
            break;
        }
        // Jika online, biarkan fallthrough ke default (404)

    // Routing API Endpoint
    case 'api/auth/google':
        require 'app/api/auth/google_redirect.php';
        break;

    case 'api/dashboard.php':
        require 'app/api/dashboard.php';
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