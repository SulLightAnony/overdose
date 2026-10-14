<?php
/**
 * MODULE: Email Blacklist API
 * FILE LOCATION: app/api/blacklist.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/gatekeeper.php';

header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserRole = $_SESSION['role_level'] ?? 'Keroco';

if (!in_array($currentUserRole, ['Primordial', 'Sepuh'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Halaman ini khusus untuk Primordial dan Sepuh.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?: [];
$input = array_merge($_GET, $_POST, $jsonInput);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'list_blacklist': {
            $search = trim((string)($input['search'] ?? ''));
            $roleFilter = trim((string)($input['blockedByRole'] ?? 'all'));
            $statusFilter = trim((string)($input['status'] ?? 'all'));
            $sort = trim((string)($input['sort'] ?? 'newest'));
            $page = max(1, (int)($input['page'] ?? 1));
            $limit = max(1, min(100, (int)($input['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            $whereClauses = ['1=1'];
            $params = [];

            if ($search !== '') {
                $whereClauses[] = '(b.emailAddress LIKE :searchEmail OR b.reasonDescription LIKE :searchReason OR u.userName LIKE :searchUser)';
                $params['searchEmail'] = "%{$search}%";
                $params['searchReason'] = "%{$search}%";
                $params['searchUser'] = "%{$search}%";
            }

            if ($roleFilter !== '' && $roleFilter !== 'all') {
                $whereClauses[] = 'b.blockedByRole = :roleFilter';
                $params['roleFilter'] = $roleFilter;
            }

            if ($statusFilter === 'registered') {
                $whereClauses[] = 'u.userId IS NOT NULL';
            } elseif ($statusFilter === 'unregistered') {
                $whereClauses[] = 'u.userId IS NULL';
            } elseif ($statusFilter === 'hard_block') {
                $whereClauses[] = 'b.isHardBlock = 1';
            } elseif ($statusFilter === 'regular') {
                $whereClauses[] = 'b.isHardBlock = 0';
            }

            $whereSql = implode(' AND ', $whereClauses);

            // Count total
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM emailblacklists b
                LEFT JOIN users u ON LOWER(b.emailAddress) = LOWER(u.emailAddress)
                WHERE {$whereSql}
            ");
            $countStmt->execute($params);
            $totalItems = (int)$countStmt->fetchColumn();
            $totalPages = ceil($totalItems / $limit);

            $orderBy = match ($sort) {
                'oldest' => 'b.createdAt ASC',
                'email_asc' => 'b.emailAddress ASC',
                'email_desc' => 'b.emailAddress DESC',
                default => 'b.createdAt DESC',
            };

            $sql = "
                SELECT 
                    b.blacklistId, b.emailAddress, b.reasonDescription, b.addedByUserId,
                    b.blockedByRole, b.isHardBlock, b.createdAt,
                    adminUser.userName AS addedByName,
                    adminUser.roleLevel AS addedByRoleLevel,
                    u.userId AS registeredUserId,
                    u.userName AS registeredUserName,
                    u.roleLevel AS registeredUserRole,
                    u.lockType AS registeredLockType
                FROM emailblacklists b
                LEFT JOIN users u ON LOWER(b.emailAddress) = LOWER(u.emailAddress)
                LEFT JOIN users adminUser ON b.addedByUserId = adminUser.userId
                WHERE {$whereSql}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $computedItems = array_map(function ($item) use ($currentUserRole) {
                $canDelete = false;
                if ($currentUserRole === 'Primordial') {
                    $canDelete = true;
                } elseif ($currentUserRole === 'Sepuh') {
                    $canDelete = ((int)$item['isHardBlock'] !== 1) && ($item['blockedByRole'] !== 'Primordial');
                }

                return array_merge($item, [
                    'isRegistered' => !empty($item['registeredUserId']),
                    'canDelete' => $canDelete
                ]);
            }, $items);

            // Summary counts
            $summaryStmt = $pdo->query("
                SELECT 
                    COUNT(*) as totalBlacklist,
                    SUM(CASE WHEN isHardBlock = 1 THEN 1 ELSE 0 END) as totalHardBlock,
                    SUM(CASE WHEN blockedByRole = 'Primordial' THEN 1 ELSE 0 END) as primordialCount,
                    SUM(CASE WHEN blockedByRole = 'Sepuh' THEN 1 ELSE 0 END) as sepuhCount
                FROM emailblacklists
            ");
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'blacklist' => $computedItems,
                    'summary' => $summary,
                    'pagination' => [
                        'totalItems' => $totalItems,
                        'totalPages' => $totalPages,
                        'currentPage' => $page,
                        'limit' => $limit
                    ]
                ]
            ]);
            exit;
        }

        case 'add_blacklist': {
            $email = strtolower(trim((string)($input['emailAddress'] ?? '')));
            $reason = trim((string)($input['reasonDescription'] ?? ''));
            $isHardBlock = !empty($input['isHardBlock']) ? 1 : 0;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Format alamat email tidak valid.']);
                exit;
            }

            // Check if user with this email exists in users table
            $checkUser = $pdo->prepare("SELECT userId, userName, roleLevel, lockType, userStatus FROM users WHERE LOWER(emailAddress) = :email LIMIT 1");
            $checkUser->execute(['email' => $email]);
            $userTarget = $checkUser->fetch(PDO::FETCH_ASSOC);

            if ($userTarget) {
                if ($userTarget['roleLevel'] === 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akun Primordial bersifat kebal dan mustahil untuk diblacklist.']);
                    exit;
                }

                if ($currentUserRole === 'Sepuh') {
                    if ($userTarget['roleLevel'] === 'Sepuh') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Sepuh tidak dapat memblacklist sesama Sepuh.']);
                        exit;
                    }

                    if ($userTarget['lockType'] === 'hard_unblock') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Pengguna ini dilindungi oleh Primordial dan tidak dapat diblacklist oleh Sepuh.']);
                        exit;
                    }
                }
            }

            if ($currentUserRole === 'Sepuh') {
                if ($reason === '') {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Alasan pemblokiran wajib diisi oleh Sepuh.']);
                    exit;
                }
                $isHardBlock = 0;
            }

            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("
                INSERT INTO emailblacklists (emailAddress, reasonDescription, addedByUserId, blockedByRole, isHardBlock)
                VALUES (:email, :reason, :actorId, :role, :isHardBlock)
                ON DUPLICATE KEY UPDATE 
                    reasonDescription = VALUES(reasonDescription),
                    addedByUserId = VALUES(addedByUserId),
                    blockedByRole = VALUES(blockedByRole),
                    isHardBlock = VALUES(isHardBlock)
            ");
            $stmtIns->execute([
                'email' => $email,
                'reason' => $reason !== '' ? $reason : null,
                'actorId' => $currentUserId,
                'role' => $currentUserRole,
                'isHardBlock' => $isHardBlock
            ]);

            // If user exists in users table, synchronize status to blocked
            if ($userTarget) {
                $targetLock = ($isHardBlock === 1) ? 'hard_block' : 'none';
                $upUser = $pdo->prepare("
                    UPDATE users 
                    SET userStatus = 'blocked', blockedByRole = :role, lockType = :lockType 
                    WHERE userId = :id
                ");
                $upUser->execute([
                    'role' => $currentUserRole,
                    'lockType' => $targetLock,
                    'id' => $userTarget['userId']
                ]);
            }

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Alamat email berhasil ditambahkan ke daftar blacklist.']);
            exit;
        }

        case 'remove_blacklist': {
            $blacklistId = (int)($input['blacklistId'] ?? 0);

            if ($blacklistId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'ID blacklist tidak valid.']);
                exit;
            }

            $checkStmt = $pdo->prepare("SELECT blacklistId, emailAddress, blockedByRole, isHardBlock FROM emailblacklists WHERE blacklistId = :id LIMIT 1");
            $checkStmt->execute(['id' => $blacklistId]);
            $bl = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bl) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Data blacklist tidak ditemukan.']);
                exit;
            }

            if ($currentUserRole === 'Sepuh') {
                if ((int)$bl['isHardBlock'] === 1 || $bl['blockedByRole'] === 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Blacklist ini dikunci oleh Primordial dan tidak dapat dihapus oleh Sepuh.']);
                    exit;
                }
            }

            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM emailblacklists WHERE blacklistId = :id");
            $delStmt->execute(['id' => $blacklistId]);

            // If user exists in users table, synchronize back to active
            $upUser = $pdo->prepare("
                UPDATE users 
                SET userStatus = 'active', blockedByRole = NULL, lockType = 'none' 
                WHERE LOWER(emailAddress) = LOWER(:email)
            ");
            $upUser->execute(['email' => $bl['emailAddress']]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Email berhasil dihapus dari blacklist dan status pengguna disinkronkan.']);
            exit;
        }

        default: {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
            exit;
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Blacklist API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memproses blacklist.']);
    exit;
}
