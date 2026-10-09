<?php
/**
 * ====================================================================================
 * MODULE: Global API (News & Community Live Chat)
 * FILE LOCATION: app/api/global.php
 * ====================================================================================
 */

require_once __DIR__ . '/../config/config.php';
init_secure_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth/gatekeeper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Unauthorized: Sesi tidak valid atau telah berakhir.'
    ]);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role_level'] ?? 'Keroco';
$isAdmin  = in_array($userRole, ['Sepuh', 'Primordial'], true);

$rawInput  = file_get_contents('php://input');
$inputData = json_decode($rawInput, true) ?: $_POST;
$action    = $_GET['action'] ?? ($inputData['action'] ?? '');

try {
    switch ($action) {
        // --- CHAT ACTIONS ---
        case 'get_chats':
            handleGetChats($pdo);
            break;

        case 'send_chat':
            handleSendChat($pdo, $userId, $inputData);
            break;

        case 'edit_chat':
            handleEditChat($pdo, $userId, $inputData);
            break;

        case 'delete_chat':
            handleDeleteChat($pdo, $userId, $isAdmin, $inputData);
            break;

        // --- GLOBAL NEWS ACTIONS ---
        case 'get_news':
            handleGetNews($pdo);
            break;

        case 'create_news':
            handleCreateNews($pdo, $userId, $inputData);
            break;

        case 'update_news':
            handleUpdateNews($pdo, $userId, $isAdmin, $inputData);
            break;

        case 'delete_news':
            handleDeleteNews($pdo, $userId, $isAdmin, $inputData);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => 'Aksi tidak valid.'
            ]);
            break;
    }
} catch (Throwable $e) {
    error_log('Global API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
    ]);
}

// ---------------------------------------------------------------------------
// CHAT HANDLERS
// ---------------------------------------------------------------------------

function getGlobalChatsColumns(PDO $pdo): array
{
    static $columns = null;
    if ($columns === null) {
        $stmt = $pdo->query("SHOW COLUMNS FROM global_chats");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $columns ?: [];
}

function handleGetChats(PDO $pdo): void
{
    // Automatic cleanup: run once per day or on initial load
    if (!isset($_SESSION['last_chat_cleanup']) || (time() - (int)$_SESSION['last_chat_cleanup']) > 86400) {
        try {
            $pdo->exec("DELETE FROM global_chats WHERE created_at < NOW() - INTERVAL 7 DAY");
            $_SESSION['last_chat_cleanup'] = time();
        } catch (Throwable $cleanupEx) {
            error_log('Chat cleanup error: ' . $cleanupEx->getMessage());
        }
    }

    $limit    = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $lastId   = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;

    $cols = getGlobalChatsColumns($pdo);
    $hasReply = in_array('reply_to_id', $cols, true);
    $hasEdited = in_array('is_edited', $cols, true);
    $hasUpdated = in_array('updated_at', $cols, true);

    $extraSelect = '';
    $extraJoin = '';

    if ($hasReply) {
        $extraSelect .= ', gc.reply_to_id, rgc.message AS reply_message, ru.userName AS reply_user_name';
        $extraJoin   .= ' LEFT JOIN global_chats rgc ON gc.reply_to_id = rgc.id LEFT JOIN users ru ON rgc.user_id = ru.userId';
    } else {
        $extraSelect .= ', NULL AS reply_to_id, NULL AS reply_message, NULL AS reply_user_name';
    }

    if ($hasEdited) {
        $extraSelect .= ', gc.is_edited';
    } else {
        $extraSelect .= ', 0 AS is_edited';
    }

    if ($hasUpdated) {
        $extraSelect .= ', gc.updated_at';
    } else {
        $extraSelect .= ', NULL AS updated_at';
    }

    if ($lastId > 0) {
        // Polling: fetch newer messages
        $stmt = $pdo->prepare("
            SELECT gc.id, gc.user_id, gc.message, gc.created_at,
                   u.userName AS user_name, u.avatarUrl AS user_avatar, u.roleLevel AS user_role
                   {$extraSelect}
            FROM global_chats gc
            LEFT JOIN users u ON gc.user_id = u.userId
            {$extraJoin}
            WHERE gc.id > :lastId
            ORDER BY gc.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':lastId', $lastId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($beforeId > 0) {
        // Lazy load older messages (before specified ID)
        $stmt = $pdo->prepare("
            SELECT gc.id, gc.user_id, gc.message, gc.created_at,
                   u.userName AS user_name, u.avatarUrl AS user_avatar, u.roleLevel AS user_role
                   {$extraSelect}
            FROM global_chats gc
            LEFT JOIN users u ON gc.user_id = u.userId
            {$extraJoin}
            WHERE gc.id < :beforeId
            ORDER BY gc.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':beforeId', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $chats = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        // Initial load: get latest 50 messages
        $stmt = $pdo->prepare("
            SELECT gc.id, gc.user_id, gc.message, gc.created_at,
                   u.userName AS user_name, u.avatarUrl AS user_avatar, u.roleLevel AS user_role
                   {$extraSelect}
            FROM global_chats gc
            LEFT JOIN users u ON gc.user_id = u.userId
            {$extraJoin}
            ORDER BY gc.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $chats = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    echo json_encode([
        'success' => true,
        'status'  => 'success',
        'data'    => $chats
    ]);
}

function handleSendChat(PDO $pdo, int $userId, array $input): void
{
    $message = trim((string)($input['message'] ?? ''));

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Pesan tidak boleh kosong.']);
        return;
    }

    $cols = getGlobalChatsColumns($pdo);
    $hasReply = in_array('reply_to_id', $cols, true);
    $hasEdited = in_array('is_edited', $cols, true);
    $hasUpdated = in_array('updated_at', $cols, true);

    $replyToId = isset($input['reply_to_id']) && (int)$input['reply_to_id'] > 0 ? (int)$input['reply_to_id'] : null;

    if ($replyToId !== null) {
        $checkStmt = $pdo->prepare("SELECT id FROM global_chats WHERE id = :reply_to_id LIMIT 1");
        $checkStmt->execute([':reply_to_id' => $replyToId]);
        if (!$checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Pesan rujukan tidak ditemukan.']);
            return;
        }
    }

    if ($hasReply) {
        $stmt = $pdo->prepare("INSERT INTO global_chats (user_id, reply_to_id, message, created_at) VALUES (:user_id, :reply_to_id, :message, NOW())");
        $stmt->execute([
            ':user_id'     => $userId,
            ':reply_to_id' => $replyToId,
            ':message'     => $message
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO global_chats (user_id, message, created_at) VALUES (:user_id, :message, NOW())");
        $stmt->execute([
            ':user_id' => $userId,
            ':message' => $message
        ]);
    }
    $newChatId = (int)$pdo->lastInsertId();

    $extraSelect = '';
    $extraJoin = '';
    if ($hasReply) {
        $extraSelect .= ', gc.reply_to_id, rgc.message AS reply_message, ru.userName AS reply_user_name';
        $extraJoin   .= ' LEFT JOIN global_chats rgc ON gc.reply_to_id = rgc.id LEFT JOIN users ru ON rgc.user_id = ru.userId';
    } else {
        $extraSelect .= ', NULL AS reply_to_id, NULL AS reply_message, NULL AS reply_user_name';
    }
    if ($hasEdited) {
        $extraSelect .= ', gc.is_edited';
    } else {
        $extraSelect .= ', 0 AS is_edited';
    }
    if ($hasUpdated) {
        $extraSelect .= ', gc.updated_at';
    } else {
        $extraSelect .= ', NULL AS updated_at';
    }

    $stmtFetch = $pdo->prepare("
        SELECT gc.id, gc.user_id, gc.message, gc.created_at,
               u.userName AS user_name, u.avatarUrl AS user_avatar, u.roleLevel AS user_role
               {$extraSelect}
        FROM global_chats gc
        LEFT JOIN users u ON gc.user_id = u.userId
        {$extraJoin}
        WHERE gc.id = :id
    ");
    $stmtFetch->execute([':id' => $newChatId]);
    $newChat = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'status' => 'success', 'data' => $newChat]);
}

function handleEditChat(PDO $pdo, int $userId, array $input): void
{
    $chatId  = (int)($input['chat_id'] ?? ($input['id'] ?? ($_GET['chat_id'] ?? ($_GET['id'] ?? 0))));
    $message = trim((string)($input['message'] ?? ''));

    if ($chatId <= 0 || $message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'ID pesan dan teks pesan wajib diisi.']);
        return;
    }

    $checkStmt = $pdo->prepare("SELECT id, user_id FROM global_chats WHERE id = :id LIMIT 1");
    $checkStmt->execute([':id' => $chatId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Pesan tidak ditemukan.']);
        return;
    }

    if ((int)$existing['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Anda tidak berhak mengedit pesan ini.']);
        return;
    }

    $cols = getGlobalChatsColumns($pdo);
    $hasEdited = in_array('is_edited', $cols, true);
    $hasReply = in_array('reply_to_id', $cols, true);
    $hasUpdated = in_array('updated_at', $cols, true);

    if ($hasEdited) {
        $updateStmt = $pdo->prepare("UPDATE global_chats SET message = :message, is_edited = 1 WHERE id = :id");
    } else {
        $updateStmt = $pdo->prepare("UPDATE global_chats SET message = :message WHERE id = :id");
    }
    $updateStmt->execute([':message' => $message, ':id' => $chatId]);

    $extraSelect = '';
    $extraJoin = '';
    if ($hasReply) {
        $extraSelect .= ', gc.reply_to_id, rgc.message AS reply_message, ru.userName AS reply_user_name';
        $extraJoin   .= ' LEFT JOIN global_chats rgc ON gc.reply_to_id = rgc.id LEFT JOIN users ru ON rgc.user_id = ru.userId';
    } else {
        $extraSelect .= ', NULL AS reply_to_id, NULL AS reply_message, NULL AS reply_user_name';
    }
    if ($hasEdited) {
        $extraSelect .= ', gc.is_edited';
    } else {
        $extraSelect .= ', 1 AS is_edited';
    }
    if ($hasUpdated) {
        $extraSelect .= ', gc.updated_at';
    } else {
        $extraSelect .= ', NULL AS updated_at';
    }

    $fetchStmt = $pdo->prepare("
        SELECT gc.id, gc.user_id, gc.message, gc.created_at,
               u.userName AS user_name, u.avatarUrl AS user_avatar, u.roleLevel AS user_role
               {$extraSelect}
        FROM global_chats gc
        LEFT JOIN users u ON gc.user_id = u.userId
        {$extraJoin}
        WHERE gc.id = :id
    ");
    $fetchStmt->execute([':id' => $chatId]);
    $updatedChat = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Pesan berhasil diperbarui.', 'data' => $updatedChat]);
}

function handleDeleteChat(PDO $pdo, int $userId, bool $isAdmin, array $input): void
{
    $id = (int)($input['chat_id'] ?? ($input['id'] ?? ($_GET['chat_id'] ?? ($_GET['id'] ?? 0))));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'ID pesan tidak valid.']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM global_chats WHERE id = :id AND (user_id = :userId OR :isAdmin = 1)");
    $stmt->execute(['id' => $id, 'userId' => $userId, 'isAdmin' => $isAdmin ? 1 : 0]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Anda tidak berhak menghapus pesan ini.']);
        return;
    }

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Pesan berhasil dihapus.']);
}

// ---------------------------------------------------------------------------
// GLOBAL NEWS HANDLERS
// ---------------------------------------------------------------------------

function handleGetNews(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        SELECT 
            n.id, n.user_id, n.title, n.content, n.created_at,
            u.userName AS author_name, u.avatarUrl AS author_avatar, u.roleLevel AS author_role
        FROM global_news n
        LEFT JOIN users u ON n.user_id = u.userId
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'status' => 'success', 'data' => $news]);
}

function handleCreateNews(PDO $pdo, int $userId, array $input): void
{
    $title   = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));

    if ($title === '' || $content === '' || mb_strlen($title) > 200) {
        http_response_code(422);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Judul dan isi berita wajib diisi (maks 200 karakter judul).']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO global_news (user_id, title, content, created_at) VALUES (:userId, :title, :content, NOW())");
    $stmt->execute(['userId' => $userId, 'title' => $title, 'content' => $content]);

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Berita global berhasil diterbitkan.']);
}

function handleUpdateNews(PDO $pdo, int $userId, bool $isAdmin, array $input): void
{
    $id      = (int)($input['id'] ?? 0);
    $title   = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));

    if ($id <= 0 || $title === '' || $content === '' || mb_strlen($title) > 200) {
        http_response_code(422);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Data berita tidak valid.']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE global_news SET title = :title, content = :content WHERE id = :id AND (user_id = :userId OR :isAdmin = 1)");
    $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id, 'userId' => $userId, 'isAdmin' => $isAdmin ? 1 : 0]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Berita tidak ditemukan atau Anda tidak berhak mengeditnya.']);
        return;
    }

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Berita global diperbarui.']);
}

function handleDeleteNews(PDO $pdo, int $userId, bool $isAdmin, array $input): void
{
    $id = (int)($input['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'ID berita tidak valid.']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM global_news WHERE id = :id AND (user_id = :userId OR :isAdmin = 1)");
    $stmt->execute(['id' => $id, 'userId' => $userId, 'isAdmin' => $isAdmin ? 1 : 0]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Berita tidak ditemukan atau Anda tidak berhak menghapusnya.']);
        return;
    }

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Berita global berhasil dihapus.']);
}
