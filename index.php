<?php
session_start();

$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';

// Normalisasi jika request menyertakan prefix 'app/' dari BASE_URL
if (str_starts_with($path, 'app/')) {
    $path = substr($path, 4);
}

switch ($path) {
    case '':
    case 'login':
        require 'app/views/login.php'; // Diubah dari pages/login.php
        break;
        
    case 'dashboard':
        require 'app/views/dashboard.php'; // Disesuaikan ke folder views
        break;
        
    case 'semester':
        require 'app/views/semester.php'; // Disesuaikan ke folder views
        break;
        
    case 'task/detail':
        require 'app/views/task_detail.php'; // Disesuaikan ke folder views
        break;
        
    case 'settings':
        require 'app/views/settings.php'; // Disesuaikan ke folder views
        break;

    // Routing API Endpoint
    case 'api/auth/google':
        require 'app/api/auth/google_redirect.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Halaman Tidak Ditemukan</h1>";
        break;
}