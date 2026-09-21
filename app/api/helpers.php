<?php
// Pastikan session sudah berjalan di setiap halaman/endpoint
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mengirimkan respon JSON berseragam dan menghentikan eksekusi skrip.
 *
 * @param bool   $status  TRUE jika berhasil, FALSE jika gagal/error.
 * @param string $message Pesan deskriptif untuk frontend/user.
 * @param mixed  $data    Data opsional yang ingin dikirimkan (array/object).
 * @param int    $code    HTTP Response Code (default: 200 OK).
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
 * Memeriksa apakah pengguna sudah terautentikasi (login).
 * Jika via API (header meminta JSON), kirim respon JSON 401.
 * Jika via browser biasa, arahkan (redirect) ke halaman login.
 */
function requireAuth(): void {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Cek apakah request datang dari Fetch/AJAX (API Call)
        $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($isApi) {
            jsonResponse(false, 'Akses ditolak. Silakan login terlebih dahulu.', null, 401);
        } else {
            header('Location: /login');
            exit;
        }
    }
}

/**
 * Memeriksa apakah pengguna memiliki role/tingkat hak akses yang diizinkan.
 * Contoh penggunaan: requireRole(['primordial', 'sepuh']);
 *
 * @param array $allowedRoles Daftar role yang diizinkan (misal: ['primordial'], ['primordial', 'sepuh']).
 */
function requireRole(array $allowedRoles): void {
    // Pastikan user sudah login terlebih dahulu
    requireAuth();

    $userRole = $_SESSION['role_level'] ?? 'member';

    if (!in_array($userRole, $allowedRoles, true)) {
        $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($isApi) {
            jsonResponse(false, 'Anda tidak memiliki hak akses (role) untuk melakukan aksi ini.', null, 403);
        } else {
            http_response_code(403);
            echo "<h1>403 - Akses Ditolak</h1><p>Halaman ini khusus untuk pengurus berkewenangan.</p>";
            exit;
        }
    }
}

/**
 * Helper opsional untuk mendapatkan data session user yang sedang login saat ini.
 *
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'userId'                => $_SESSION['user_id'],
        'userName'              => $_SESSION['user_name'] ?? '',
        'emailAddress'          => $_SESSION['email_address'] ?? '',
        'roleLevel'             => $_SESSION['role_level'] ?? 'member',
        'avatarUrl'             => $_SESSION['avatar_url'] ?? '',
        'majorType'             => $_SESSION['major_type'] ?? null,
        'studyProgram'          => $_SESSION['study_program'] ?? null,
        'classGroup'            => $_SESSION['class_group'] ?? null,
        'batchYear'             => $_SESSION['batch_year'] ?? null,
        'hideCompletedIdentity' => $_SESSION['hide_completed_identity'] ?? 0
    ];
}