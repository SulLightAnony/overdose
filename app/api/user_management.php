<?php
/**
 * MODULE: User Management API
 * FILE LOCATION: app/api/user_management.php
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
        case 'list_users': {
            $search = trim((string)($input['search'] ?? ''));
            $roleFilter = trim((string)($input['role'] ?? 'all'));
            $statusFilter = trim((string)($input['status'] ?? 'all'));
            $sort = trim((string)($input['sort'] ?? 'newest'));
            $page = max(1, (int)($input['page'] ?? 1));
            $limit = max(1, min(100, (int)($input['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            $whereClauses = ['1=1'];
            $params = [];

            if ($search !== '') {
                $whereClauses[] = '(u.userName LIKE :searchName OR u.emailAddress LIKE :searchEmail)';
                $params['searchName'] = "%{$search}%";
                $params['searchEmail'] = "%{$search}%";
            }

            if ($roleFilter !== '' && $roleFilter !== 'all') {
                $whereClauses[] = 'u.roleLevel = :roleFilter';
                $params['roleFilter'] = $roleFilter;
            }

            if ($statusFilter !== '' && $statusFilter !== 'all') {
                $whereClauses[] = 'u.userStatus = :statusFilter';
                $params['statusFilter'] = $statusFilter;
            }

            $whereSql = implode(' AND ', $whereClauses);

            // Count total
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
            $countStmt->execute($params);
            $totalItems = (int)$countStmt->fetchColumn();
            $totalPages = ceil($totalItems / $limit);

            // Determine sort SQL
            $orderBy = match ($sort) {
                'oldest' => 'u.createdAt ASC',
                'name_asc' => 'u.userName ASC',
                'name_desc' => 'u.userName DESC',
                'role' => "FIELD(u.roleLevel, 'Primordial', 'Sepuh', 'Keroco'), u.userName ASC",
                default => 'u.createdAt DESC',
            };

            $sql = "
                SELECT 
                    u.userId, u.userName, u.emailAddress, u.avatarUrl, u.roleLevel,
                    u.majorType, u.studyProgram, u.classGroup, u.batchYear,
                    u.userStatus, u.blockedByRole, u.lockType, u.createdAt,
                    eb.reasonDescription AS blockReason,
                    eb.isHardBlock,
                    eb.createdAt AS blockedAt,
                    adminUser.userName AS blockedByName
                FROM users u
                LEFT JOIN emailblacklists eb ON LOWER(u.emailAddress) = LOWER(eb.emailAddress)
                LEFT JOIN users adminUser ON eb.addedByUserId = adminUser.userId
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
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute permissions per item
            $computedUsers = array_map(function ($u) use ($currentUserId, $currentUserRole) {
                $targetId = (int)$u['userId'];
                $targetRole = $u['roleLevel'];
                $targetStatus = $u['userStatus'];
                $targetLock = $u['lockType'];
                $isSelf = ($targetId === $currentUserId);
                $isTargetPrimordial = ($targetRole === 'Primordial');

                $canChangeRole = ($currentUserRole === 'Primordial') && !$isSelf && !$isTargetPrimordial;

                $canBlock = false;
                if (!$isSelf && !$isTargetPrimordial && $targetStatus !== 'blocked') {
                    if ($currentUserRole === 'Primordial') {
                        $canBlock = true;
                    } elseif ($currentUserRole === 'Sepuh') {
                        $canBlock = ($targetRole === 'Keroco') && ($targetLock !== 'hard_unblock');
                    }
                }

                $canUnblock = false;
                if ($targetStatus === 'blocked') {
                    if ($currentUserRole === 'Primordial') {
                        $canUnblock = true;
                    } elseif ($currentUserRole === 'Sepuh') {
                        $canUnblock = ($targetLock !== 'hard_block') && ((int)($u['isHardBlock'] ?? 0) !== 1) && ($u['blockedByRole'] !== 'Primordial');
                    }
                }

                $canToggleHardUnblock = ($currentUserRole === 'Primordial') && !$isSelf && !$isTargetPrimordial && ($targetStatus === 'active');

                return array_merge($u, [
                    'canChangeRole' => $canChangeRole,
                    'canBlock' => $canBlock,
                    'canUnblock' => $canUnblock,
                    'canToggleHardUnblock' => $canToggleHardUnblock,
                ]);
            }, $users);

            // Summary counts
            $summaryStmt = $pdo->query("
                SELECT 
                    COUNT(*) as totalUsers,
                    SUM(CASE WHEN userStatus = 'active' THEN 1 ELSE 0 END) as activeUsers,
                    SUM(CASE WHEN userStatus = 'blocked' THEN 1 ELSE 0 END) as blockedUsers,
                    SUM(CASE WHEN roleLevel = 'Sepuh' THEN 1 ELSE 0 END) as sepuhUsers,
                    SUM(CASE WHEN roleLevel = 'Keroco' THEN 1 ELSE 0 END) as kerocoUsers
                FROM users
            ");
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => $computedUsers,
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

        case 'get_user_detail': {
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Parameter ID pengguna tidak valid.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    u.userId, u.userName, u.emailAddress, u.avatarUrl, u.roleLevel,
                    u.majorType, u.studyProgram, u.classGroup, u.batchYear,
                    u.userStatus, u.blockedByRole, u.lockType, u.createdAt, u.updatedAt,
                    eb.reasonDescription AS blockReason,
                    eb.isHardBlock,
                    eb.createdAt AS blockedAt,
                    adminUser.userName AS blockedByName,
                    adminUser.roleLevel AS blockedByAdminRole
                FROM users u
                LEFT JOIN emailblacklists eb ON LOWER(u.emailAddress) = LOWER(eb.emailAddress)
                LEFT JOIN users adminUser ON eb.addedByUserId = adminUser.userId
                WHERE u.userId = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                exit;
            }

            $targetId = (int)$user['userId'];
            $targetRole = $user['roleLevel'];
            $targetStatus = $user['userStatus'];
            $targetLock = $user['lockType'];
            $isSelf = ($targetId === $currentUserId);
            $isTargetPrimordial = ($targetRole === 'Primordial');

            $canChangeRole = ($currentUserRole === 'Primordial') && !$isSelf && !$isTargetPrimordial;

            $canBlock = false;
            if (!$isSelf && !$isTargetPrimordial && $targetStatus !== 'blocked') {
                if ($currentUserRole === 'Primordial') {
                    $canBlock = true;
                } elseif ($currentUserRole === 'Sepuh') {
                    $canBlock = ($targetRole === 'Keroco') && ($targetLock !== 'hard_unblock');
                }
            }

            $canUnblock = false;
            if ($targetStatus === 'blocked') {
                if ($currentUserRole === 'Primordial') {
                    $canUnblock = true;
                } elseif ($currentUserRole === 'Sepuh') {
                    $canUnblock = ($targetLock !== 'hard_block') && ((int)($user['isHardBlock'] ?? 0) !== 1) && ($user['blockedByRole'] !== 'Primordial');
                }
            }

            $canToggleHardUnblock = ($currentUserRole === 'Primordial') && !$isSelf && !$isTargetPrimordial && ($targetStatus === 'active');

            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => array_merge($user, [
                        'canChangeRole' => $canChangeRole,
                        'canBlock' => $canBlock,
                        'canUnblock' => $canUnblock,
                        'canToggleHardUnblock' => $canToggleHardUnblock
                    ])
                ]
            ]);
            exit;
        }

        case 'update_user_role': {
            if ($currentUserRole !== 'Primordial') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Hanya Primordial yang memiliki izin mengubah peran pengguna.']);
                exit;
            }

            $targetUserId = (int)($input['targetUserId'] ?? 0);
            $newRole = trim((string)($input['newRole'] ?? ''));

            if ($targetUserId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'ID pengguna target tidak valid.']);
                exit;
            }

            if ($targetUserId === $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak dapat mengubah peran Anda sendiri.']);
                exit;
            }

            if (!in_array($newRole, ['Sepuh', 'Keroco'], true)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Pilihan peran hanya boleh Sepuh atau Keroco.']);
                exit;
            }

            $checkStmt = $pdo->prepare("SELECT roleLevel FROM users WHERE userId = :id LIMIT 1");
            $checkStmt->execute(['id' => $targetUserId]);
            $target = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                exit;
            }

            if ($target['roleLevel'] === 'Primordial') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akun Primordial kebal dan perannya tidak dapat diubah.']);
                exit;
            }

            $upStmt = $pdo->prepare("UPDATE users SET roleLevel = :role WHERE userId = :id");
            $upStmt->execute(['role' => $newRole, 'id' => $targetUserId]);

            echo json_encode(['success' => true, 'message' => "Peran pengguna berhasil diubah menjadi {$newRole}."]);
            exit;
        }

        case 'block_user': {
            $targetUserId = (int)($input['targetUserId'] ?? 0);
            $reason = trim((string)($input['reasonDescription'] ?? ''));
            $lockType = trim((string)($input['lockType'] ?? 'none'));

            if ($targetUserId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'ID pengguna target tidak valid.']);
                exit;
            }

            if ($targetUserId === $currentUserId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Anda tidak dapat memblokir akun Anda sendiri.']);
                exit;
            }

            $checkStmt = $pdo->prepare("SELECT userId, emailAddress, roleLevel, userStatus, lockType FROM users WHERE userId = :id LIMIT 1");
            $checkStmt->execute(['id' => $targetUserId]);
            $target = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                exit;
            }

            if ($target['roleLevel'] === 'Primordial') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akun Primordial bersifat kebal dan mustahil untuk diblokir.']);
                exit;
            }

            if ($currentUserRole === 'Sepuh') {
                if ($target['roleLevel'] !== 'Keroco') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Sepuh hanya diizinkan untuk memblokir pengguna berlevel Keroco.']);
                    exit;
                }

                if ($target['lockType'] === 'hard_unblock') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Pengguna ini dilindungi oleh Primordial dan tidak dapat diblokir oleh Sepuh.']);
                    exit;
                }

                if ($reason === '') {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => 'Alasan pemblokiran wajib diisi oleh Sepuh.']);
                    exit;
                }

                $lockType = 'none';
            }

            if ($currentUserRole === 'Primordial') {
                if (!in_array($lockType, ['none', 'hard_block'], true)) {
                    $lockType = 'none';
                }
            }

            $isHardBlock = ($lockType === 'hard_block') ? 1 : 0;

            $pdo->beginTransaction();

            $upUser = $pdo->prepare("
                UPDATE users 
                SET userStatus = 'blocked', blockedByRole = :role, lockType = :lockType 
                WHERE userId = :id
            ");
            $upUser->execute([
                'role' => $currentUserRole,
                'lockType' => $lockType,
                'id' => $targetUserId
            ]);

            $insBl = $pdo->prepare("
                INSERT INTO emailblacklists (emailAddress, reasonDescription, addedByUserId, blockedByRole, isHardBlock)
                VALUES (:email, :reason, :actorId, :role, :isHardBlock)
                ON DUPLICATE KEY UPDATE 
                    reasonDescription = VALUES(reasonDescription),
                    addedByUserId = VALUES(addedByUserId),
                    blockedByRole = VALUES(blockedByRole),
                    isHardBlock = VALUES(isHardBlock)
            ");
            $insBl->execute([
                'email' => $target['emailAddress'],
                'reason' => $reason !== '' ? $reason : null,
                'actorId' => $currentUserId,
                'role' => $currentUserRole,
                'isHardBlock' => $isHardBlock
            ]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Pengguna berhasil diblokir dan disinkronkan ke daftar blacklist.']);
            exit;
        }

        case 'unblock_user': {
            $targetUserId = (int)($input['targetUserId'] ?? 0);
            $newLockType = trim((string)($input['newLockType'] ?? 'none'));

            if ($targetUserId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'ID pengguna target tidak valid.']);
                exit;
            }

            $checkStmt = $pdo->prepare("
                SELECT u.userId, u.emailAddress, u.roleLevel, u.userStatus, u.blockedByRole, u.lockType, eb.isHardBlock
                FROM users u
                LEFT JOIN emailblacklists eb ON LOWER(u.emailAddress) = LOWER(eb.emailAddress)
                WHERE u.userId = :id 
                LIMIT 1
            ");
            $checkStmt->execute(['id' => $targetUserId]);
            $target = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
                exit;
            }

            if ($currentUserRole === 'Sepuh') {
                if ($target['lockType'] === 'hard_block' || (int)($target['isHardBlock'] ?? 0) === 1 || $target['blockedByRole'] === 'Primordial') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Pengguna ini dikunci oleh Primordial dan tidak dapat dibuka blokirnya oleh Sepuh.']);
                    exit;
                }
                $newLockType = 'none';
            }

            if ($currentUserRole === 'Primordial') {
                if (!in_array($newLockType, ['none', 'hard_unblock'], true)) {
                    $newLockType = 'none';
                }
            }

            $pdo->beginTransaction();

            $upUser = $pdo->prepare("
                UPDATE users 
                SET userStatus = 'active', blockedByRole = NULL, lockType = :lockType 
                WHERE userId = :id
            ");
            $upUser->execute([
                'lockType' => $newLockType,
                'id' => $targetUserId
            ]);

            $delBl = $pdo->prepare("DELETE FROM emailblacklists WHERE LOWER(emailAddress) = LOWER(:email)");
            $delBl->execute(['email' => $target['emailAddress']]);

            $pdo->commit();

            $msg = ($newLockType === 'hard_unblock')
                ? 'Blokir pengguna berhasil dibuka dan diproteksi (Hard Unblock).'
                : 'Blokir pengguna berhasil dibuka kembali.';

            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        case 'set_hard_unblock': {
            if ($currentUserRole !== 'Primordial') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses khusus untuk Primordial.']);
                exit;
            }

            $targetUserId = (int)($input['targetUserId'] ?? 0);
            $lockType = trim((string)($input['lockType'] ?? 'none'));

            if (!in_array($lockType, ['none', 'hard_unblock'], true)) {
                $lockType = 'none';
            }

            $stmt = $pdo->prepare("UPDATE users SET lockType = :lockType WHERE userId = :id AND roleLevel != 'Primordial' AND userStatus = 'active'");
            $stmt->execute(['lockType' => $lockType, 'id' => $targetUserId]);

            echo json_encode(['success' => true, 'message' => 'Status proteksi pengguna berhasil diperbarui.']);
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
    error_log('User Management API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memproses data.']);
    exit;
}
