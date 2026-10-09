<?php
require_once __DIR__ . '/../api/auth/gatekeeper.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../api/db.php';

// Pastikan data profil & avatar tersinkron dari DB (menggunakan userName, bukan name)
if (isset($_SESSION['user_id'])) {
    $stmtNavUser = $pdo->prepare("SELECT userName, avatarUrl FROM users WHERE userId = :userId LIMIT 1");
    $stmtNavUser->execute(['userId' => $_SESSION['user_id']]);
    $navUserData = $stmtNavUser->fetch(PDO::FETCH_ASSOC);
    
    if ($navUserData) {
        $userName   = $navUserData['userName'];
        $userAvatar = !empty($navUserData['avatarUrl']) ? $navUserData['avatarUrl'] : BASE_URL . 'public/assets/img/logo.png';
    }
}

$userName        = $userName ?? ($_SESSION['user_name'] ?? 'Pengguna');
$userAvatar      = $userAvatar ?? ($_SESSION['user_avatar'] ?? BASE_URL . 'public/assets/img/logo.png');
$userRole        = $_SESSION['role_level'] ?? 'Keroco';
$hasNotification = $_SESSION['has_unread_notification'] ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Overdose' : 'Overdose' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css">
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/logo.png" type="image/png">
    <script>
        function formatMultiFileSize(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function getUniqueFileName(originalName, existingNames) {
            if (!existingNames.has(originalName)) {
                return originalName;
            }
            const dotIndex = originalName.lastIndexOf('.');
            const baseName = dotIndex !== -1 ? originalName.substring(0, dotIndex) : originalName;
            const ext = dotIndex !== -1 ? originalName.substring(dotIndex) : '';
            
            let counter = 1;
            let candidate = `${baseName} (${counter})${ext}`;
            while (existingNames.has(candidate)) {
                counter++;
                candidate = `${baseName} (${counter})${ext}`;
            }
            return candidate;
        }

        window.initMultiFileInputs = function(scope = document) {
            scope.querySelectorAll('input[type="file"][multiple]:not([data-multi-file-ready])').forEach(input => {
                input.dataset.multiFileReady = '1';
                let transfer = new DataTransfer();
                const list = document.createElement('div');
                list.className = 'mt-2 d-flex flex-column gap-1';

                let insertTarget = input;
                const nextEl = input.nextElementSibling;
                if (nextEl && (nextEl.classList.contains('form-text') || nextEl.tagName === 'SMALL')) {
                    insertTarget = nextEl;
                } else {
                    const fallbackHelper = document.createElement('div');
                    fallbackHelper.className = 'form-text text-muted';
                    fallbackHelper.textContent = 'Maksimal 5 file, ukuran per file maksimal 10MB.';
                    input.insertAdjacentElement('afterend', fallbackHelper);
                    insertTarget = fallbackHelper;
                }
                insertTarget.insertAdjacentElement('afterend', list);

                const render = () => {
                    list.replaceChildren();
                    const files = Array.from(transfer.files);

                    if (files.length > 0) {
                        const summary = document.createElement('div');
                        summary.className = 'd-flex justify-content-between align-items-center mb-1 text-muted';
                        summary.style.fontSize = '0.78rem';
                        summary.innerHTML = `<span class="fw-semibold">File dipilih (${files.length}/5):</span><span class="text-muted">Maks. 10MB/file</span>`;
                        list.appendChild(summary);
                    }

                    files.forEach((file, index) => {
                        const row = document.createElement('div');
                        row.className = 'd-flex align-items-center justify-content-between bg-light rounded-3 px-3 py-2 border-0 shadow-none';
                        row.style.border = 'none';
                        row.style.boxShadow = 'none';
                        
                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'text-truncate me-2 small d-flex align-items-center flex-grow-1';
                        
                        const icon = document.createElement('i');
                        icon.className = 'bi bi-file-earmark-check text-primary me-2 flex-shrink-0';
                        
                        const nameSpan = document.createElement('span');
                        nameSpan.className = 'fw-medium text-dark text-truncate';
                        nameSpan.textContent = file.name;

                        const sizeSpan = document.createElement('span');
                        sizeSpan.className = 'text-muted ms-1 flex-shrink-0';
                        sizeSpan.style.fontSize = '0.75rem';
                        sizeSpan.textContent = `(${formatMultiFileSize(file.size)})`;

                        infoDiv.appendChild(icon);
                        infoDiv.appendChild(nameSpan);
                        infoDiv.appendChild(sizeSpan);

                        const btnRemove = document.createElement('button');
                        btnRemove.type = 'button';
                        btnRemove.className = 'btn btn-sm btn-outline-danger border-0 rounded-circle p-0 d-flex align-items-center justify-content-center flex-shrink-0 shadow-none';
                        btnRemove.style.width = '22px';
                        btnRemove.style.height = '22px';
                        btnRemove.title = 'Hapus file';
                        btnRemove.setAttribute('aria-label', 'Hapus file');
                        btnRemove.innerHTML = '<i class="bi bi-x-lg" style="font-size: 0.7rem;"></i>';

                        btnRemove.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            removeFile(index);
                        });

                        row.appendChild(infoDiv);
                        row.appendChild(btnRemove);
                        list.appendChild(row);
                    });
                };

                const removeFile = (indexToRemove) => {
                    const newTransfer = new DataTransfer();
                    Array.from(transfer.files).forEach((f, idx) => {
                        if (idx !== indexToRemove) {
                            newTransfer.items.add(f);
                        }
                    });
                    transfer = newTransfer;
                    input._multiFileTransfer = transfer;
                    input.files = transfer.files;
                    render();
                };

                input.addEventListener('change', () => {
                    const newFiles = Array.from(input.files || []);
                    if (!newFiles.length) return;

                    const maxFiles = 5;
                    const maxSizeBytes = 10 * 1024 * 1024; // 10MB
                    let currentCount = transfer.files.length;
                    let rejectedSizeFiles = [];
                    let rejectedQuotaCount = 0;

                    const existingNames = new Set(Array.from(transfer.files).map(f => f.name));

                    for (const file of newFiles) {
                        if (currentCount >= maxFiles) {
                            rejectedQuotaCount++;
                            continue;
                        }

                        if (file.size > maxSizeBytes) {
                            rejectedSizeFiles.push(file.name);
                            continue;
                        }

                        let fileToAdd = file;
                        const uniqueName = getUniqueFileName(file.name, existingNames);
                        if (uniqueName !== file.name) {
                            fileToAdd = new File([file], uniqueName, {
                                type: file.type,
                                lastModified: file.lastModified
                            });
                        }

                        existingNames.add(uniqueName);
                        transfer.items.add(fileToAdd);
                        currentCount++;
                    }

                    if (rejectedSizeFiles.length > 0) {
                        const msg = rejectedSizeFiles.length === 1
                            ? `File "${rejectedSizeFiles[0]}" melebihi batas 10MB dan tidak ditambahkan.`
                            : `${rejectedSizeFiles.length} file melebihi batas 10MB dan tidak ditambahkan.`;
                        window.appToast?.(msg, 'warning');
                    }

                    if (rejectedQuotaCount > 0) {
                        window.appToast?.(`Batas maksimal 5 file tercapai. ${rejectedQuotaCount} file lainnya tidak ditambahkan.`, 'warning');
                    }

                    input.files = transfer.files;
                    render();
                });

                input.dataset.multiFileList = '1';
                input._multiFileTransfer = transfer;
                input._multiFileRender = render;

                const form = input.closest('form');
                if (form) {
                    if (!form.dataset.multiFileResetReady) {
                        form.dataset.multiFileResetReady = '1';
                        form.addEventListener('reset', () => window.setTimeout(() => window.clearMultiFileInputs(form), 0));
                    }
                    form.addEventListener('submit', () => {
                        input.files = transfer.files;
                    });
                }
            });
        };
        window.clearMultiFileInputs = function(form) {
            form?.querySelectorAll('input[type="file"][data-multi-file-ready]').forEach(input => {
                const transfer = new DataTransfer();
                input.files = transfer.files;
                input._multiFileTransfer = transfer;
                input._multiFileRender?.();
            });
        };
        document.addEventListener('DOMContentLoaded', () => window.initMultiFileInputs());
        document.addEventListener('click', async event => {
            const button = event.target.closest('[data-share-url]');
            if (!button) return;
            try {
                await navigator.clipboard.writeText(button.dataset.shareUrl || window.location.href);
                window.appToast?.('URL telah disalin', 'success');
            } catch (error) {
                window.appToast?.('URL tidak dapat disalin dari browser ini.', 'warning');
            }
        });
    </script>
</head>
<body>

<div class="app-wrapper">

    <!-- NAVBAR KIRI / SIDEBAR -->
    <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
        
        <!-- Single Brand Logo Top Header -->
        <div class="sidebar-header p-3 d-flex align-items-center justify-content-between position-relative border-bottom border-secondary border-opacity-25 flex-shrink-0">
            <a href="<?= BASE_URL ?>dashboard" class="d-flex align-items-center justify-content-center w-100 text-decoration-none">
                <img src="<?= BASE_URL ?>public/assets/img/logo_title.png" alt="Overdose Logo" class="sidebar-brand-logo">
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none position-absolute top-50 end-0 translate-middle-y me-3" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>

        <!-- Menu Navigasi -->
        <div class="offcanvas-body d-flex flex-column p-3 overflow-hidden h-100">
            <div class="sidebar-nav-scroll flex-grow-1 overflow-y-auto pe-1">
                <ul class="nav nav-pills flex-column gap-1 w-100">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>dashboard" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>schedule" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'schedule') ? 'active' : '' ?>">
                        <i class="bi bi-calendar3"></i>
                        <span>Jadwal</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>tasks" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'tasks') ? 'active' : '' ?>">
                        <i class="bi bi-card-checklist"></i>
                        <span>Daftar Tugas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>semester" class="nav-link <?= (isset($activeMenu) && in_array($activeMenu, ['semester', 'course'], true)) ? 'active' : '' ?>">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span>Semester & Matkul</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>news" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'news') ? 'active' : '' ?>">
                        <i class="bi bi-newspaper"></i>
                        <span>Berita</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>global" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'global') ? 'active' : '' ?>">
                        <i class="bi bi-globe"></i>
                        <span>Global</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>configurations" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'configurations') ? 'active' : '' ?>">
                        <i class="bi bi-gear-fill"></i>
                        <span>Pengaturan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>bug-report" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'bug-report') ? 'active' : '' ?>">
                        <i class="bi bi-bug-fill"></i>
                        <span>Bug & Report</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>traktir" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'traktir') ? 'active' : '' ?>">
                        <i class="bi bi-cup-hot-fill"></i>
                        <span>Traktir</span>
                    </a>
                </li>
                <?php if ($userRole === 'Primordial' || $userRole === 'Sepuh'): ?>
                <li class="my-2">
                    <hr class="border-secondary opacity-25 m-0">
                </li>
                <?php if ($userRole === 'Primordial'): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>user-management" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'user-management') ? 'active' : '' ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Pengguna</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>blacklist" class="nav-link <?= (isset($activeMenu) && $activeMenu === 'blacklist') ? 'active' : '' ?>">
                        <i class="bi bi-shield-x"></i>
                        <span>Blacklist Email</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            </div>
        </div>

        <!-- Tombol Keluar (Memicu Modal) -->
        <div class="sidebar-footer p-3 border-top border-secondary border-opacity-25 flex-shrink-0">
            <button type="button" class="nav-link nav-link-logout w-100 text-start border-0" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="bi bi-box-arrow-left"></i>
                <span>Keluar</span>
            </button>
        </div>
    </aside>

    <!-- AREA UTAMA -->
    <div class="app-main">
        
        <!-- TOPBAR -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-nav-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <i class="bi bi-list fs-4"></i>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Ikon Lonceng Notifikasi -->
                <div class="dropdown">
                    <button type="button" class="btn btn-nav-icon position-relative" id="notificationDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi">
                        <i class="bi bi-bell fs-5"></i>
                        <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0" id="notificationDropdown" aria-labelledby="notificationDropdownBtn" style="width: min(360px, calc(100vw - 2rem));">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>Notifikasi</strong>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnMarkAllRead">Tandai dibaca</button>
                        </div>
                        <div id="notificationListContainer" class="overflow-auto" style="max-height: 420px;">
                            <div class="text-center p-3 text-muted">Memuat notifikasi...</div>
                        </div>
                    </div>
                </div>

                <!-- Avatar Profil Pengguna Google -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar-circle" referrerpolicy="no-referrer">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" aria-labelledby="userDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-semibold text-dark small"><?= htmlspecialchars($userName) ?></p>
                        </li>
                        <li>
                            <a class="dropdown-item small py-2" href="<?= BASE_URL ?>configurations">
                                <i class="bi bi-person me-2"></i> Pengaturan Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item small py-2 text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="bi bi-box-arrow-left me-2"></i> Keluar
                            </button>
                        </li>
                    </ul>
                </div>

            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main id="app-content">
            <?php 
            if (isset($viewContentPath) && file_exists($viewContentPath)) {
                require_once $viewContentPath;
            }
            echo $content ?? ''; 
            ?>
        </main>

    </div>

</div>

<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="appConfirmTitle">Konfirmasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body" id="appConfirmMessage"></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger" id="appConfirmAccept">Lanjutkan</button></div>
    </div></div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090">
    <div id="appToast" class="toast" role="status" aria-live="polite" aria-atomic="true"><div class="toast-body" id="appToastMessage"></div></div>
</div>

<!-- MODAL KONFIRMASI LOGOUT -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4 p-2 text-center">
            <div class="modal-body">
                <i class="bi bi-exclamation-circle text-warning fs-1 mb-2 d-block"></i>
                <h6 class="fw-bold text-dark mb-1">Konfirmasi Keluar</h6>
                <p class="text-secondary small mb-4">Apakah kamu yakin ingin keluar dari akun ini?</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border w-50 py-2 rounded-3 fw-semibold small" data-bs-dismiss="modal">Batal</button>
                    <a href="<?= BASE_URL ?>logout" class="btn btn-danger w-50 py-2 rounded-3 fw-semibold small text-decoration-none">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.appConfirm = function(message, title = 'Konfirmasi') {
        return new Promise(resolve => {
            const modalElement = document.getElementById('appConfirmModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            const acceptButton = document.getElementById('appConfirmAccept');
            document.getElementById('appConfirmTitle').textContent = title;
            document.getElementById('appConfirmMessage').textContent = message;
            const finish = value => {
                acceptButton.removeEventListener('click', onAccept);
                modalElement.removeEventListener('hidden.bs.modal', onCancel);
                resolve(value);
            };
            const onAccept = () => { modal.hide(); finish(true); };
            const onCancel = () => finish(false);
            acceptButton.addEventListener('click', onAccept, { once: true });
            modalElement.addEventListener('hidden.bs.modal', onCancel, { once: true });
            modal.show();
        });
    };
    window.appToast = function(message, type = 'primary') {
        const toastElement = document.getElementById('appToast');
        const toastBody = document.getElementById('appToastMessage');
        if (!toastElement || !toastBody) return;
        toastElement.className = `toast text-bg-${type}`;
        toastBody.textContent = message;
        bootstrap.Toast.getOrCreateInstance(toastElement, { delay: 3200 }).show();
    };

    // Auto-scroll sidebar navigation to active item (instant, no smooth animation)
    function scrollActiveNavLinkIntoView() {
        const activeLink = document.querySelector('.sidebar-nav-scroll .nav-link.active');
        if (activeLink) {
            activeLink.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }
    }
    document.addEventListener('DOMContentLoaded', scrollActiveNavLinkIntoView);

    const sidebarMenuElement = document.getElementById('sidebarMenu');
    if (sidebarMenuElement) {
        sidebarMenuElement.addEventListener('shown.bs.offcanvas', scrollActiveNavLinkIntoView);
    }
</script>
<script src="<?= BASE_URL ?>public/js/modules/notifications.js"></script>

</body>
</html>