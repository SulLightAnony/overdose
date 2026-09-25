<?php
/**
 * ====================================================================================
 * MODULE: Backend API Configurations & User Management
 * FILE LOCATION: app/api/configurations.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File API ini menangani seluruh manajemen preferensi pengguna (Toggle Notifikasi & Mode Anonim),
 * tindakan Hapus Akun Mandiri (Hard Delete), serta menyediakan fitur CRUD Manajemen Pengguna 
 * dan Blacklist Email khusus peran Primordial dan Sepuh dengan aturan Penguncian Hierarki.
 * 
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. users:
 *    - Read/Update: isAnonymous, enableNotifications, userStatus, blockedByRole, roleLevel.
 *    - Delete: Hard Delete row user (FK ON DELETE SET NULL menjaga data buatan user).
 * 2. emailblacklists:
 *    - Read/Insert/Delete: blacklistId, emailAddress, reasonDescription, addedByUserId, blockedByRole.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/configurations.js
 * - Auth Gatekeeper: app/api/auth/gatekeeper.php
 * - DB Connection: app/api/db.php, app/config/config.php
 * 
 * LOGIKA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi & Gatekeeper:
 *    - Memastikan session user aktif dan terverifikasi via gatekeeper.php.
 * 
 * 2. ACTION ROUTING (via parameter 'action'):
 *    a) action = 'get_profile' (GET - Semua User):
 *       - Mengambil data profil user aktif (nama, email, avatar, role, isAnonymous, enableNotifications).
 * 
 *    b) action = 'update_preferences' (POST - Semua User):
 *       - Menerima payload `isAnonymous` (0/1) dan `enableNotifications` (0/1).
 *       - Mengubah kolom `isAnonymous` dan `enableNotifications` pada tabel `users`.
 * 
 *    c) action = 'delete_account' (POST/DELETE - Semua User):
 *       - Menghapus baris user secara fisik (`DELETE FROM users WHERE userId = :userId`).
 *       - Karena FK diatur `ON DELETE SET NULL`, data tugas, materi, dan komentar buatan user TETAP UTUH.
 *       - Menghancurkan session (`session_destroy()`) dan mereset status login.
 * 
 *    d) action = 'list_users' (GET - Primordial & Sepuh Only):
 *       - Ambil daftar seluruh user untuk tabel manajemen pengguna.
 *       - Sepuh hanya bisa melihat list, Primordial bisa melihat & mengubah role/status.
 * 
 *    e) action = 'update_user_role' (POST - Primordial Only):
 *       - Mengubah `roleLevel` target user ('Primordial', 'Sepuh', 'Keroco').
 * 
 *    f) action = 'toggle_user_block' (POST - Primordial & Sepuh):
 *       - Blokir/Buka Blokir user target (`userStatus` = 'active'/'blocked').
 *       - PROTEKSI HIERARKI: Jika user diblokir oleh Primordial (`blockedByRole = 'Primordial'`), 
 *         maka Sepuh TIDAK BISA membuka blokir user tersebut (return HTTP 403 / error message).
 *       - Jika tindakan dilakukan oleh Primordial, set `blockedByRole = 'Primordial'`. Jika oleh Sepuh, set `blockedByRole = 'Sepuh'`.
 * 
 *    g) action = 'list_blacklist' (GET - Primordial & Sepuh):
 *       - Ambil daftar seluruh email terblokir pada tabel `emailblacklists`.
 * 
 *    h) action = 'add_blacklist' (POST - Primordial & Sepuh):
 *       - Menambahkan email baru ke `emailblacklists` beserta alasan dan `blockedByRole` (role aktor aktif).
 * 
 *    i) action = 'remove_blacklist' (POST/DELETE - Primordial & Sepuh):
 *       - Menghapus email dari `emailblacklists`.
 *       - PROTEKSI HIERARKI: Jika email diblacklist oleh Primordial (`blockedByRole = 'Primordial'`),
 *         maka Sepuh TIDAK BISA menghapus blacklist tersebut.
 * 
 * ATURAN SECURITY & FORMAT OUTPUT:
 * - Selalu gunakan PDO Prepared Statements.
 * - Format JSON output: ['success' => bool, 'message' => string, 'data' => mixed].
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/gatekeeper.php'; // Handle HTTP 401 & 403

header('Content-Type: application/json');

$userId = intval($_SESSION['user_id']);
$userRole = $_SESSION['role_level'] ?? 'Keroco';
$method =$_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ?$_GET['action'] : (isset($_POST['action']) ?$_POST['action'] : '');

$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($jsonInput['action'])) {
    $action =$jsonInput['action'];
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'get_profile') {
                $stmt =$pdo->prepare("SELECT userName, emailAddress, avatarUrl, roleLevel, isAnonymous, enableNotifications FROM users WHERE userId = :userId LIMIT 1");
                $stmt->execute(['userId' =>$userId]);
                $profile =$stmt->fetch(PDO::FETCH_ASSOC);

                if ($profile) {
                    echo json_encode(['success' => true, 'message' => 'Profil berhasil dimuat.', 'data' => $profile]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                }
                exit;
            } 
            
            elseif ($action === 'list_users') {
                if ($userRole !== 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin.']);
                    exit;
                }

                $stmt =$pdo->prepare("SELECT userId, userName, emailAddress, roleLevel, userStatus, blockedByRole, createdAt FROM users ORDER BY roleLevel ASC, createdAt DESC");
                $stmt->execute();
                $users =$stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'message' => 'Daftar pengguna berhasil dimuat.', 'data' => ['users' => $users]]);
                exit;
            }

            elseif ($action === 'list_blacklist') {
                if ($userRole !== 'Primordial' &&$userRole !== 'Sepuh') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin.']);
                    exit;
                }

                $stmt =$pdo->prepare("SELECT b.blacklistId, b.emailAddress, b.reasonDescription, b.createdAt, b.blockedByRole, u.userName as addedByName 
                                       FROM emailblacklists b 
                                       LEFT JOIN users u ON b.addedByUserId = u.userId 
                                       ORDER BY b.createdAt DESC");
                $stmt->execute();
                $blacklist =$stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'message' => 'Daftar blacklist berhasil dimuat.', 'data' => ['blacklist' => $blacklist]]);
                exit;
            }
            break;

        case 'POST':
            if ($action === 'update_preferences') {
                $isAnonymous = isset($_POST['isAnonymous']) ? intval($_POST['isAnonymous']) : (isset($jsonInput['isAnonymous']) ? intval($jsonInput['isAnonymous']) : 0);
                $enableNotifications = isset($_POST['enableNotifications']) ? intval($_POST['enableNotifications']) : (isset($jsonInput['enableNotifications']) ? intval($jsonInput['enableNotifications']) : 1);

                if (!in_array($isAnonymous, [0, 1], true) || !in_array($enableNotifications, [0, 1], true)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Nilai preferensi tidak valid.']);
                    exit;
                }

                $stmt =$pdo->prepare("UPDATE users SET isAnonymous = :isAnon, enableNotifications = :enableNotif WHERE userId = :userId");
                $stmt->execute([
                    'isAnon' => $isAnonymous,
                    'enableNotif' => $enableNotifications,
                    'userId' => $userId
                ]);

                echo json_encode(['success' => true, 'message' => 'Preferensi berhasil diperbarui.']);
                exit;
            }

            elseif ($action === 'update_user_role') {
                if ($userRole !== 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Hanya Primordial yang dapat mengubah peran pengguna.']);
                    exit;
                }

                $targetUserId = isset($jsonInput['targetUserId']) ? intval($jsonInput['targetUserId']) : 0;
                $newRole = isset($jsonInput['newRole']) ?$jsonInput['newRole'] : '';

                if (!in_array($newRole, ['Primordial', 'Sepuh', 'Keroco'])) {
                    echo json_encode(['success' => false, 'message' => 'Peran tidak valid.']);
                    exit;
                }

                if ($targetUserId ===$userId) {
                    echo json_encode(['success' => false, 'message' => 'Anda tidak dapat mengubah peran Anda sendiri.']);
                    exit;
                }

                $stmt =$pdo->prepare("UPDATE users SET roleLevel = :newRole WHERE userId = :targetId");
                $stmt->execute(['newRole' => $newRole, 'targetId' =>$targetUserId]);

                echo json_encode(['success' => true, 'message' => 'Peran pengguna berhasil diperbarui.']);
                exit;
            }

            elseif ($action === 'toggle_user_block') {
                if ($userRole !== 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                    exit;
                }

                $targetUserId = isset($jsonInput['targetUserId']) ? intval($jsonInput['targetUserId']) : 0;
                $newStatus = isset($jsonInput['newStatus']) ?$jsonInput['newStatus'] : ''; // 'active' atau 'blocked'

                if (!in_array($newStatus, ['active', 'blocked'], true)) {
                    echo json_encode(['success' => false, 'message' => 'Status pengguna tidak valid.']);
                    exit;
                }

                if ($targetUserId ===$userId) {
                    echo json_encode(['success' => false, 'message' => 'Anda tidak dapat memblokir diri sendiri.']);
                    exit;
                }

                // Proteksi Hierarki (Anti-Override Sepuh)
                $stmtCheck =$pdo->prepare("SELECT blockedByRole, roleLevel FROM users WHERE userId = :targetId LIMIT 1");
                $stmtCheck->execute(['targetId' =>$targetUserId]);
                $targetUser =$stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$targetUser) {
                    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                    exit;
                }

                if ($targetUser['roleLevel'] === 'Primordial' &&$userRole === 'Sepuh') {
                    echo json_encode(['success' => false, 'message' => 'Sepuh tidak dapat memblokir Primordial.']);
                    exit;
                }

                if ($newStatus === 'active' && $userRole === 'Sepuh' &&$targetUser['blockedByRole'] === 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Akun ini dikunci oleh Primordial.']);
                    exit;
                }

                $blockedBy = ($newStatus === 'blocked') ?$userRole : null;

                $stmt =$pdo->prepare("UPDATE users SET userStatus = :newStatus, blockedByRole = :blockedBy WHERE userId = :targetId");
                $stmt->execute([
                    'newStatus' => $newStatus,
                    'blockedBy' => $blockedBy,
                    'targetId' => $targetUserId
                ]);

                echo json_encode(['success' => true, 'message' => "Status pengguna berhasil diubah menjadi $newStatus."]);
                exit;
            }

            elseif ($action === 'add_blacklist') {
                if ($userRole !== 'Primordial' &&$userRole !== 'Sepuh') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                    exit;
                }

                $email = isset($jsonInput['emailAddress']) ? trim($jsonInput['emailAddress']) : '';$reason = isset($jsonInput['reasonDescription']) ? trim($jsonInput['reasonDescription']) : '';

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
                    exit;
                }

                $stmtCheck =$pdo->prepare("SELECT blacklistId FROM emailblacklists WHERE emailAddress = :email");
                $stmtCheck->execute(['email' =>$email]);
                if ($stmtCheck->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email sudah ada di daftar blacklist.']);
                    exit;
                }

                $stmt =$pdo->prepare("INSERT INTO emailblacklists (emailAddress, reasonDescription, addedByUserId, blockedByRole) VALUES (:email, :reason, :userId, :role)");
                $stmt->execute([
                    'email' => $email,
                    'reason' => $reason,
                    'userId' => $userId,
                    'role' => $userRole
                ]);

                echo json_encode(['success' => true, 'message' => 'Email berhasil ditambahkan ke blacklist.']);
                exit;
            }
            
            // Delete account via POST action just in case DELETE method isn't supported gracefully
            elseif ($action === 'delete_account') {
                goto execute_delete_account;
            }
            break;

        case 'DELETE':
            if ($action === 'remove_blacklist') {
                if ($userRole !== 'Primordial' &&$userRole !== 'Sepuh') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
                    exit;
                }

                $blacklistId = isset($jsonInput['blacklistId']) ? intval($jsonInput['blacklistId']) : 0;

                // Proteksi Hierarki
                $stmtCheck =$pdo->prepare("SELECT blockedByRole FROM emailblacklists WHERE blacklistId = :id");
                $stmtCheck->execute(['id' =>$blacklistId]);
                $bl =$stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$bl) {
                    echo json_encode(['success' => false, 'message' => 'Data blacklist tidak ditemukan.']);
                    exit;
                }

                if ($userRole === 'Sepuh' &&$bl['blockedByRole'] === 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Blacklist ini dikunci oleh Primordial.']);
                    exit;
                }

                $stmt =$pdo->prepare("DELETE FROM emailblacklists WHERE blacklistId = :id");
                $stmt->execute(['id' =>$blacklistId]);

                echo json_encode(['success' => true, 'message' => 'Email berhasil dihapus dari blacklist.']);
                exit;
            }

            elseif ($action === 'delete_account') {
                execute_delete_account:
                // Hard delete row pengguna dari DB. 
                // FK rules (ON DELETE SET NULL) akan menjaga data akademik tetap aman.
                $stmt =$pdo->prepare("DELETE FROM users WHERE userId = :userId");
                $stmt->execute(['userId' =>$userId]);

                // Putus session
                session_unset();
                session_destroy();

                echo json_encode(['success' => true, 'message' => 'Akun Anda berhasil dihapus secara permanen.']);
                exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.']);
            exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    exit;

} catch (Throwable $e) {
    error_log("Configurations API DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server.']);
    exit;
}
?>