<?php
session_start();

$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';

// Routing Halaman Utama / Views
switch ($path) {
    case '':
    case 'login':
        require 'pages/login.php';
        break;
        
    case 'dashboard':
        require 'pages/dashboard.php';
        break;
        
    case 'semester':
        require 'pages/semester.php';
        break;
        
    case 'task/detail':
        require 'pages/task_detail.php';
        break;
        
    case 'settings':
        require 'pages/settings.php';
        break;

    // Routing API Endpoint
    case 'api/auth/google':
        require 'api/auth_callback.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Halaman Tidak Ditemukan</h1>";
        break;
}
?>