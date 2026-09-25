<?php
// Memastikan config terjangkau agar BASE_URL selalu terdefinisi
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mengirimkan respon JSON berseragam dan menghentikan eksekusi skrip.
 */
function jsonResponse(bool $status, string $message, $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Memeriksa apakah permintaan saat ini menuju endpoint API
 */
function isApiRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($uri, '/api/') || str_contains($accept, 'application/json');
}

/**
 * Memeriksa apakah pengguna sudah terautentikasi (login)
 */
function requireAuth(): void {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        if (isApiRequest()) {
            jsonResponse(false, 'Akses ditolak. Silakan login terlebih dahulu.', null, 401);
        } else {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }
    }
}

/**
 * Memeriksa apakah pengguna memiliki role/tingkat hak akses yang diizinkan
 */
function requireRole(array $allowedRoles): void {
    requireAuth();

    $userRole = $_SESSION['role_level'] ?? 'Keroco';

    if (!in_array($userRole, $allowedRoles, true)) {
        if (isApiRequest()) {
            jsonResponse(false, 'Anda tidak memiliki hak akses (role) untuk melakukan aksi ini.', null, 403);
        } else {
            http_response_code(403);
            echo "<h1>403 - Akses Ditolak</h1><p>Halaman ini khusus untuk pengurus berkewenangan.</p>";
            exit;
        }
    }
}

/**
 * Mendapatkan data sesi pengguna saat ini
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'userId'                => $_SESSION['user_id'],
        'userName'              => $_SESSION['user_name'] ?? '',
        'emailAddress'          => $_SESSION['email_address'] ?? '',
        'roleLevel'             => $_SESSION['role_level'] ?? 'Keroco',
        'avatarUrl'             => $_SESSION['avatar_url'] ?? '',
        'majorType'             => $_SESSION['major_type'] ?? null,
        'studyProgram'          => $_SESSION['study_program'] ?? null,
        'classGroup'            => $_SESSION['class_group'] ?? null,
        'batchYear'             => $_SESSION['batch_year'] ?? null,
        'hideCompletedIdentity' => $_SESSION['hide_completed_identity'] ?? 0
    ];
}