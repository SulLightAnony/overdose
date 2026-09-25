<?php
/**
 * ====================================================================================
 * MODULE: Backend API Notifications (In-App, Push Subscriptions, & Lazy Deadline Check)
 * FILE LOCATION: app/api/notifications.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini berfungsi sebagai API Endpoint terpusat untuk mengelola seluruh sistem notifikasi
 * aplikasi. Meliputi pendaftaran subskripsi Web Push VAPID per perangkat pengguna,
 * pengambilan daftar notifikasi in-app, perhitungan jumlah notifikasi belum dibaca (unread count),
 * perubah status baca (isRead), serta eksekusi pemicu otomatis "Lazy Background Check" untuk
 * mendeteksi tugas mendekati deadline (H-1) dan tugas terlewat (Overdue).
 * 
 * RELASI DATABASE (DATABASE RELATIONS):
 * 1. notifications (Main Notification Table):
 *    - PK: notificationId
 *    - FK: userId -> users.userId (ON DELETE CASCADE)
 *    - FK: relatedTaskId -> tasks.taskId (ON DELETE SET NULL)
 * 2. user_push_subscriptions (Web Push Credentials):
 *    - PK: subscriptionId
 *    - FK: userId -> users.userId (ON DELETE CASCADE)
 * 3. tasks & courses (Data Source for Lazy Check):
 *    - Membaca daftar tugas aktif (`deletionStatus = 0`) untuk mengecek `dueDate`.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Callers / Consumer: public/js/modules/notifications.js, public/js/modules/configurations.js
 * - Background Listener: public/sw.js (Service Worker)
 * - Helper / DB: app/api/db.php, app/config/config.php, app/api/auth/gatekeeper.php
 * 
 * LOGIKA & ALUR EKSEKUSI (HOW IT WORKS):
 * 1. Otentikasi & Session Validation:
 *    - Memeriksa session aktif $_SESSION['user_id']. Jika tidak valid/ditemukan, return HTTP 401 JSON.
 *    - Memeriksa status user di DB. Jika status = 'blocked', putus session & return HTTP 403.
 * 
 * 2. GET REQUEST ROUTING (via parameter 'action'):
 *    a) action = 'list':
 *       - Mengambil daftar notifikasi milik pengguna aktif (`userId = $_SESSION['user_id']`).
 *       - Urutkan dari yang terbaru (`createdAt DESC`).
 *       - Mendukung pagination/limit (default 15 data terbaru).
 *    b) action = 'unread_count':
 *       - Menghitung jumlah baris notifikasi dengan `isRead = 0` untuk pengguna aktif.
 *       - Dipanggil secara berkala oleh JS untuk merender badge angka/titik merah (Red Dot) di topbar.
 * 
 * 3. POST REQUEST ROUTING (via parameter 'action'):
 *    a) action = 'subscribe_push':
 *       - Menerima payload JSON/POST: `endpoint`, `p256dhKey`, `authToken`.
 *       - Memeriksa apakah subskripsi endpoint tersebut sudah ada di `user_push_subscriptions`.
 *       - Jika belum ada, simpan data subskripsi baru berelasi dengan `userId` aktif.
 *    b) action = 'mark_as_read':
 *       - Menerima `notificationId` (Opsional). Jika `notificationId` diberikan, update `isRead = 1` untuk ID tersebut.
 *       - Jika `notificationId` tidak diberikan atau bernilai 'all', update SELURUH notifikasi milik user aktif menjadi `isRead = 1`.
 *    c) action = 'lazy_check_deadlines':
 *       - Memicu pengecekan otomatis berbasis background setiap kali user menavigasi/membuka aplikasi.
 *       - Cari seluruh tugas aktif (`deletionStatus = 0`) pada semester aktif pengguna.
 *       - Pemicu 1 (Pengingat H-1): Jika selisih `dueDate` dengan waktu sekarang <= 24 jam dan > 0 jam, 
 *         cek apakah notifikasi tipe 'reminder_h1' untuk `taskId` ini sudah pernah dibuat untuk user.
 *         Jika belum, insert row notifikasi baru: "Pengingat: Tugas [taskTitle] tersisa < 24 jam!".
 *       - Pemicu 2 (Tugas Terlewat / Overdue): Jika `dueDate` < waktu sekarang dan tugas belum ditandai selesai (`isCompleted = 0`),
 *         cek apakah notifikasi tipe 'overdue' sudah dibuat. Jika belum, insert row notifikasi baru.
 * 
 * ATURAN SECURITY & FORMAT OUTPUT:
 * - Gunakan PDO Prepared Statements pada semua kueri database.
 * - Format output JSON konsisten: ['success' => bool, 'message' => string, 'data' => mixed].
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/gatekeeper.php'; // Handle HTTP 401 & 403 blocked user

header('Content-Type: application/json');

$userId = intval($_SESSION['user_id']);
$method =$_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ?$_GET['action'] : (isset($_POST['action']) ?$_POST['action'] : '');

// Menerima raw JSON input jika form data biasa kosong
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($jsonInput['action'])) {
    $action =$jsonInput['action'];
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 15;
                $offset = ($page - 1) *$limit;

                $stmt =$pdo->prepare("SELECT notificationId, notificationTitle, notificationMessage, notificationType, relatedTaskId, targetUrl, isRead, createdAt FROM notifications WHERE userId = :userId ORDER BY createdAt DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);$stmt->execute();
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Cek ketersediaan halaman selanjutnya
                $stmtCount =$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = :userId");
                $stmtCount->execute(['userId' =>$userId]);
                $totalRows =$stmtCount->fetchColumn();
                $hasMore = ($offset + $limit) <$totalRows;

                echo json_encode([
                    'success' => true,
                    'message' => 'Daftar notifikasi berhasil diambil.',
                    'data' => [
                        'notifications' => $notifications,
                        'hasMore' => $hasMore
                    ]
                ]);
                exit;
            } 
            
            elseif ($action === 'unread_count') {
                $stmt =$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = :userId AND isRead = 0");
                $stmt->execute(['userId' =>$userId]);
                $count =$stmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'message' => 'Total unread notifications retrieved.',
                    'data' => ['unreadCount' => intval($count)]
                ]);
                exit;
            } 
            
            elseif ($action === 'lazy_check_deadlines') {
                // Ambil profil user untuk mencocokkan tugas matkul
                $stmtUser =$pdo->prepare("SELECT majorType, studyProgram, classGroup, batchYear, enableNotifications FROM users WHERE userId = :userId LIMIT 1");
                $stmtUser->execute(['userId' =>$userId]);
                $user =$stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$user || (int)$user['enableNotifications'] === 0) {
                    echo json_encode(['success' => true, 'message' => 'Notifikasi dimatikan atau user tidak valid.']);
                    exit;
                }

                $insertedCount = 0;

                // 1. Pengingat H-1 (Sisa < 24 jam & belum selesai)
                $sqlH1 = "SELECT t.taskId, t.taskTitle, t.dueDate 
                          FROM tasks t 
                          JOIN courses c ON t.courseId = c.courseId
                          WHERE t.deletionStatus = 0 
                            AND c.majorType = :majorType AND c.studyProgram = :studyProgram 
                            AND c.classGroup = :classGroup AND c.batchYear = :batchYear
                            AND t.dueDate BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                            AND NOT EXISTS (
                                SELECT 1 FROM task_completions tc WHERE tc.taskId = t.taskId AND tc.userId = :completionUserId
                            )
                            AND NOT EXISTS (
                                SELECT 1 FROM notifications n WHERE n.relatedTaskId = t.taskId AND n.userId = :notificationUserId AND n.notificationType = 'reminder_h1'
                            )";
                $stmtH1 =$pdo->prepare($sqlH1);$stmtH1->execute([
                    'majorType' => $user['majorType'],
                    'studyProgram' => $user['studyProgram'],
                    'classGroup' => $user['classGroup'],
                    'batchYear' => $user['batchYear'],
                    'completionUserId' => $userId,
                    'notificationUserId' => $userId
                ]);
                $tasksH1 =$stmtH1->fetchAll(PDO::FETCH_ASSOC);

                foreach ($tasksH1 as$t) {
                    $insertH1 =$pdo->prepare("INSERT IGNORE INTO notifications (userId, notificationTitle, notificationMessage, notificationType, relatedTaskId, targetUrl) VALUES (:userId, :title, :message, 'reminder_h1', :taskId, :url)");
                    $insertH1->execute([
                        'userId' => $userId,
                        'title' => 'Pengingat Deadline Tugas!',
                        'message' => 'Tugas "' . $t['taskTitle'] . '" akan segera berakhir dalam kurang dari 24 jam. Segera kerjakan!',
                        'taskId' => $t['taskId'],
                        'url' => BASE_URL . 'app/views/task_detail.php?id=' . $t['taskId']
                    ]);
                    $insertedCount += $insertH1->rowCount();
                }

                // 2. Tugas Terlewat (Overdue & belum selesai)
                $sqlOverdue = "SELECT t.taskId, t.taskTitle, t.dueDate 
                               FROM tasks t 
                               JOIN courses c ON t.courseId = c.courseId
                               WHERE t.deletionStatus = 0 
                                 AND c.majorType = :majorType AND c.studyProgram = :studyProgram 
                                 AND c.classGroup = :classGroup AND c.batchYear = :batchYear
                                 AND t.dueDate < NOW()
                                 AND NOT EXISTS (
                                     SELECT 1 FROM task_completions tc WHERE tc.taskId = t.taskId AND tc.userId = :completionUserId
                                 )
                                 AND NOT EXISTS (
                                     SELECT 1 FROM notifications n WHERE n.relatedTaskId = t.taskId AND n.userId = :notificationUserId AND n.notificationType = 'overdue'
                                 )";
                $stmtOverdue =$pdo->prepare($sqlOverdue);$stmtOverdue->execute([
                    'majorType' => $user['majorType'],
                    'studyProgram' => $user['studyProgram'],
                    'classGroup' => $user['classGroup'],
                    'batchYear' => $user['batchYear'],
                    'completionUserId' => $userId,
                    'notificationUserId' => $userId
                ]);
                $tasksOverdue =$stmtOverdue->fetchAll(PDO::FETCH_ASSOC);

                foreach ($tasksOverdue as$t) {
                    $insertOverdue =$pdo->prepare("INSERT IGNORE INTO notifications (userId, notificationTitle, notificationMessage, notificationType, relatedTaskId, targetUrl) VALUES (:userId, :title, :message, 'overdue', :taskId, :url)");
                    $insertOverdue->execute([
                        'userId' => $userId,
                        'title' => 'Tugas Telah Terlewat',
                        'message' => 'Batas waktu untuk tugas "' . $t['taskTitle'] . '" telah lewat. Cek detail tugas untuk informasi lebih lanjut.',
                        'taskId' => $t['taskId'],
                        'url' => BASE_URL . 'app/views/task_detail.php?id=' . $t['taskId']
                    ]);
                    $insertedCount += $insertOverdue->rowCount();
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Lazy check selesai.',
                    'data' => ['newNotificationsGenerated' => $insertedCount]
                ]);
                exit;
            }
            break;

        case 'POST':
            if ($action === 'subscribe_push') {$endpoint = isset($jsonInput['endpoint']) ?$jsonInput['endpoint'] : '';
                $p256dh = isset($jsonInput['p256dhKey']) ? $jsonInput['p256dhKey'] : '';$auth = isset($jsonInput['authToken']) ?$jsonInput['authToken'] : '';

                if (empty($endpoint) || empty($p256dh) || empty($auth)) {
                    echo json_encode(['success' => false, 'message' => 'Data kredensial Push tidak lengkap.']);
                    exit;
                }

                // Cek apakah endpoint sudah terdaftar untuk user ini
                $stmtCheck =$pdo->prepare("SELECT subscriptionId FROM user_push_subscriptions WHERE userId = :userId AND endpoint = :endpoint LIMIT 1");
                $stmtCheck->execute(['userId' => $userId, 'endpoint' =>$endpoint]);

                if (!$stmtCheck->fetch()) {
                    $stmtInsert =$pdo->prepare("INSERT INTO user_push_subscriptions (userId, endpoint, p256dhKey, authToken) VALUES (:userId, :endpoint, :p256dhKey, :authToken)");
                    $stmtInsert->execute([
                        'userId' => $userId,
                        'endpoint' => $endpoint,
                        'p256dhKey' => $p256dh,
                        'authToken' => $auth
                    ]);
                }

                echo json_encode(['success' => true, 'message' => 'Push subscription berhasil disimpan.']);
                exit;
            } 
            
            elseif ($action === 'mark_as_read') {
                $notificationId = isset($_POST['notificationId']) ?$_POST['notificationId'] : (isset($jsonInput['notificationId']) ?$jsonInput['notificationId'] : 'all');

                if ($notificationId === 'all') {
                    $stmt =$pdo->prepare("UPDATE notifications SET isRead = 1 WHERE userId = :userId AND isRead = 0");
                    $stmt->execute(['userId' =>$userId]);
                } else {
                    $stmt =$pdo->prepare("UPDATE notifications SET isRead = 1 WHERE userId = :userId AND notificationId = :notificationId");
                    $stmt->execute(['userId' =>$userId, 'notificationId' => intval($notificationId)]);
                }

                echo json_encode(['success' => true, 'message' => 'Status notifikasi berhasil diperbarui.']);
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
    error_log("Notifications API DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan internal server.']);
    exit;
}
?>