document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('usersTableBody');
    const alertBox = document.getElementById('userManagementAlert');
    const searchInput = document.getElementById('searchUser');
    const filterRole = document.getElementById('filterRole');
    const filterStatus = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('sortUsers');
    const resetBtn = document.getElementById('btnResetFilters');
    const paginationContainer = document.getElementById('userPaginationContainer');

    // Stats
    const statTotal = document.getElementById('statTotalUsers');
    const statActive = document.getElementById('statActiveUsers');
    const statBlocked = document.getElementById('statBlockedUsers');
    const statSepuh = document.getElementById('statSepuhUsers');

    // Modals
    const modalBlockEl = document.getElementById('modalBlockUser');
    const modalBlock = modalBlockEl ? bootstrap.Modal.getOrCreateInstance(modalBlockEl) : null;
    const formBlock = document.getElementById('formBlockUser');

    const modalUnblockEl = document.getElementById('modalUnblockUser');
    const modalUnblock = modalUnblockEl ? bootstrap.Modal.getOrCreateInstance(modalUnblockEl) : null;
    const formUnblock = document.getElementById('formUnblockUser');

    const modalRoleEl = document.getElementById('modalChangeRole');
    const modalRole = modalRoleEl ? bootstrap.Modal.getOrCreateInstance(modalRoleEl) : null;
    const formRole = document.getElementById('formChangeRole');

    let currentPage = 1;
    let searchDebounceTimer = null;
    let cachedUsers = [];

    const defaultAvatar = `${BASE_URL}public/assets/img/logo.png`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

    const notify = (message, type = 'success') => {
        if (window.appToast) {
            window.appToast(message, type);
        } else if (alertBox) {
            alertBox.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    };

    function renderRoleBadge(role) {
        if (!role) return '';
        const lower = role.toLowerCase();
        if (lower === 'primordial') {
            return `<span class="badge" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.72rem; font-weight: 600;">Primordial</span>`;
        }
        if (lower === 'sepuh') {
            return `<span class="badge bg-secondary text-white" style="font-size: 0.72rem;">Sepuh</span>`;
        }
        return `<span class="badge bg-light text-dark border" style="font-size: 0.72rem;">Keroco</span>`;
    }

    function renderStatusBadge(user) {
        const status = user.userStatus || 'active';
        const lock = user.lockType || 'none';
        const isHard = (Number(user.isHardBlock) === 1);

        if (status === 'blocked') {
            if (lock === 'hard_block' || isHard) {
                return `<div class="d-flex flex-column gap-1">
                    <span class="badge bg-danger rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Diblokir</span>
                    <span class="badge bg-dark rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                        <i class="bi bi-lock-fill me-1"></i>Hard Block
                    </span>
                </div>`;
            }
            return `<div class="d-flex flex-column gap-1">
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Diblokir</span>
                ${user.blockedByRole ? `<span class="text-muted" style="font-size: 0.68rem;">Oleh ${escapeHtml(user.blockedByRole)}</span>` : ''}
            </div>`;
        }

        if (lock === 'hard_unblock') {
            return `<div class="d-flex flex-column gap-1">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Aktif</span>
                <span class="badge bg-success text-white rounded-pill px-2 py-0.5" style="font-size: 0.65rem;" title="Dilindungi Primordial">
                    <i class="bi bi-shield-lock-fill me-1"></i>Hard Unblock
                </span>
            </div>`;
        }

        return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Aktif</span>`;
    }

    function formatDate(rawDate) {
        if (!rawDate) return '-';
        try {
            const date = new Date(rawDate.replace(/-/g, '/'));
            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        } catch (e) {
            return rawDate;
        }
    }

    async function request(url, options = {}) {
        const response = await fetch(url, options);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan gagal diproses.');
        return data;
    }

    async function loadUsers() {
        if (!tableBody) return;

        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                    Memuat data pengguna...
                </td>
            </tr>`;

        const search = searchInput?.value.trim() || '';
        const role = filterRole?.value || 'all';
        const status = filterStatus?.value || 'all';
        const sort = sortSelect?.value || 'newest';

        const params = new URLSearchParams({
            action: 'list_users',
            search: search,
            role: role,
            status: status,
            sort: sort,
            page: currentPage,
            limit: 10
        });

        try {
            const result = await request(`${BASE_URL}app/api/user_management.php?${params.toString()}`);
            const users = result.data?.users || [];
            cachedUsers = users;
            const pagination = result.data?.pagination || { totalItems: 0, totalPages: 1, currentPage: 1, limit: 10 };
            const summary = result.data?.summary || {};

            // Update stats
            if (statTotal) statTotal.textContent = summary.totalUsers || 0;
            if (statActive) statActive.textContent = summary.activeUsers || 0;
            if (statBlocked) statBlocked.textContent = summary.blockedUsers || 0;
            if (statSepuh) statSepuh.textContent = summary.sepuhUsers || 0;

            if (!users.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-2 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-semibold text-dark mb-1">Pengguna Tidak Ditemukan</h6>
                            <p class="small text-muted mb-0">Tidak ada pengguna yang cocok dengan kriteria pencarian atau filter.</p>
                        </td>
                    </tr>`;
                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            tableBody.innerHTML = users.map(user => {
                const uId = Number(user.userId);
                const avatar = user.avatarUrl || defaultAvatar;

                // Format identitas akademik dengan menyertakan majorType di depan kelas
                let classPart = '';
                if (user.majorType && user.classGroup) {
                    classPart = `${user.majorType}-${user.classGroup}`;
                } else if (user.classGroup) {
                    classPart = user.classGroup;
                } else if (user.majorType) {
                    classPart = user.majorType;
                }
                const academicParts = [classPart, user.studyProgram].filter(Boolean);
                const academicStr = academicParts.length ? academicParts.join(' - ') : (user.majorType || 'Civitas Akademika');
                const dateStr = formatDate(user.createdAt);

                return `
                    <tr class="user-row" data-user-id="${uId}" style="cursor: pointer;">
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <!-- Foto profil dengan badge level di bawahnya (tengah) -->
                                <div class="d-flex flex-column align-items-center flex-shrink-0" style="min-width: 68px;">
                                    <img src="${escapeHtml(avatar)}" alt="${escapeHtml(user.userName)}" class="rounded-circle border" style="width: 44px; height: 44px; object-fit: cover;" referrerpolicy="no-referrer" onerror="this.src='${defaultAvatar}'">
                                    <div class="mt-1 text-center w-100">
                                        ${renderRoleBadge(user.roleLevel)}
                                    </div>
                                </div>
                                <!-- Informasi nama & akademik pengguna: memanjang ke kanan dan fleksibel membungkus jika sempit -->
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <a href="${BASE_URL}user-detail?id=${uId}" class="fw-bold text-dark text-decoration-none user-link d-inline-block" style="font-size: 0.88rem; word-break: break-word;" title="${escapeHtml(user.userName)}">
                                        ${escapeHtml(user.userName)}
                                    </a>
                                    <div class="text-muted small" style="font-size: 0.78rem; word-break: break-all;">${escapeHtml(user.emailAddress)}</div>
                                    <div class="text-secondary" style="font-size: 0.72rem;">${escapeHtml(academicStr)}</div>
                                </div>
                            </div>
                        </td>
                        <td>${renderStatusBadge(user)}</td>
                        <td class="text-muted small">${escapeHtml(dateStr)}</td>
                        <td class="text-end pe-4" onclick="event.stopPropagation();">
                            <div class="d-inline-flex align-items-center gap-1.5 flex-wrap justify-content-end">
                                <a href="${BASE_URL}user-detail?id=${uId}" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" style="font-size: 0.75rem;" title="Lihat Profil & Riwayat Aktivitas">
                                    <i class="bi bi-eye"></i> Detail
                                </a>

                                ${user.canChangeRole ? `
                                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-2.5 fw-semibold" style="font-size: 0.75rem;" data-btn-role="${uId}" title="Ubah Peran">
                                        <i class="bi bi-person-gear"></i> Role
                                    </button>
                                ` : ''}

                                ${user.canBlock ? `
                                    <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold" style="font-size: 0.75rem;" data-btn-block="${uId}">
                                        <i class="bi bi-slash-circle"></i> Blokir
                                    </button>
                                ` : ''}

                                ${user.canUnblock ? `
                                    <button type="button" class="btn btn-outline-success rounded-pill btn-sm px-3 fw-semibold" style="font-size: 0.75rem;" data-btn-unblock="${uId}">
                                        <i class="bi bi-check-circle"></i> Buka
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            renderPagination(pagination);

        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div></td></tr>`;
        }
    }

    function hardScrollToPagination(container) {
        if (!container) return;
        const targetId = container.id || 'userPaginationContainer';
        window.location.hash = targetId;
        container.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        setTimeout(() => {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }, 150);
    }

    function renderPagination(pg) {
        if (!paginationContainer) return;
        if (pg.totalPages <= 1) {
            paginationContainer.innerHTML = `<span class="small text-muted">Menampilkan ${pg.totalItems} pengguna</span>`;
            return;
        }

        let html = `<div class="small text-muted">Halaman ${pg.currentPage} dari ${pg.totalPages} (${pg.totalItems} total pengguna)</div>`;
        html += `<ul class="pagination pagination-sm mb-0">`;

        // Prev
        html += `<li class="page-item ${pg.currentPage <= 1 ? 'disabled' : ''}">
            <button class="page-link rounded-pill px-3 me-1" data-page="${pg.currentPage - 1}">&laquo;</button>
        </li>`;

        const start = Math.max(1, pg.currentPage - 2);
        const end = Math.min(pg.totalPages, pg.currentPage + 2);

        for (let p = start; p <= end; p++) {
            html += `<li class="page-item ${p === pg.currentPage ? 'active' : ''}">
                <button class="page-link rounded-circle mx-0.5 text-center" style="width: 32px; height: 32px; padding: 0; line-height: 32px;" data-page="${p}">${p}</button>
            </li>`;
        }

        // Next
        html += `<li class="page-item ${pg.currentPage >= pg.totalPages ? 'disabled' : ''}">
            <button class="page-link rounded-pill px-3 ms-1" data-page="${pg.currentPage + 1}">&raquo;</button>
        </li>`;

        html += `</ul>`;
        paginationContainer.innerHTML = html;
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', async e => {
            const btn = e.target.closest('[data-page]');
            if (!btn || btn.parentElement.classList.contains('disabled')) return;
            const targetPage = Number(btn.dataset.page);
            if (targetPage && targetPage > 0 && targetPage !== currentPage) {
                currentPage = targetPage;
                await loadUsers();
                hardScrollToPagination(paginationContainer);
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                currentPage = 1;
                loadUsers();
            }, 300);
        });
    }

    if (filterRole) {
        filterRole.addEventListener('change', () => {
            currentPage = 1;
            loadUsers();
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            currentPage = 1;
            loadUsers();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            currentPage = 1;
            loadUsers();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (filterRole) filterRole.value = 'all';
            if (filterStatus) filterStatus.value = 'all';
            if (sortSelect) sortSelect.value = 'newest';
            currentPage = 1;
            loadUsers();
        });
    }

    // Row click navigation to user-detail
    if (tableBody) {
        tableBody.addEventListener('click', event => {
            // Block/Unblock/Role buttons have event.stopPropagation()
            const row = event.target.closest('tr.user-row');
            if (row && row.dataset.userId) {
                window.location.href = `${BASE_URL}user-detail?id=${row.dataset.userId}`;
            }
        });
    }

    // Handle Action Buttons Click
    document.addEventListener('click', event => {
        // Block button
        const blockBtn = event.target.closest('[data-btn-block]');
        if (blockBtn && modalBlock) {
            const userId = Number(blockBtn.dataset.btnBlock);
            const user = cachedUsers.find(u => Number(u.userId) === userId);
            if (!user) return;

            document.getElementById('blockTargetUserId').value = user.userId;
            document.getElementById('blockUserName').textContent = user.userName;
            document.getElementById('blockUserEmail').textContent = user.emailAddress;
            document.getElementById('blockUserAvatar').src = user.avatarUrl || defaultAvatar;
            document.getElementById('blockReasonDescription').value = '';
            
            const normalRadio = document.getElementById('blockTypeNormal');
            if (normalRadio) normalRadio.checked = true;

            modalBlock.show();
            return;
        }

        // Unblock button
        const unblockBtn = event.target.closest('[data-btn-unblock]');
        if (unblockBtn && modalUnblock) {
            const userId = Number(unblockBtn.dataset.btnUnblock);
            const user = cachedUsers.find(u => Number(u.userId) === userId);
            if (!user) return;

            document.getElementById('unblockTargetUserId').value = user.userId;
            document.getElementById('unblockUserName').textContent = user.userName;
            document.getElementById('unblockUserEmail').textContent = user.emailAddress;
            document.getElementById('unblockUserAvatar').src = user.avatarUrl || defaultAvatar;

            const normalRadio = document.getElementById('unblockTypeNormal');
            if (normalRadio) normalRadio.checked = true;

            modalUnblock.show();
            return;
        }

        // Role change button (Primordial)
        const roleBtn = event.target.closest('[data-btn-role]');
        if (roleBtn && modalRole) {
            const userId = Number(roleBtn.dataset.btnRole);
            const user = cachedUsers.find(u => Number(u.userId) === userId);
            if (!user) return;

            document.getElementById('roleTargetUserId').value = user.userId;
            document.getElementById('roleUserName').textContent = user.userName;
            document.getElementById('roleUserCurrent').textContent = `Peran Saat Ini: ${user.roleLevel}`;
            document.getElementById('roleUserAvatar').src = user.avatarUrl || defaultAvatar;
            
            const selectRole = document.getElementById('selectNewRole');
            if (selectRole) {
                selectRole.value = (user.roleLevel === 'Sepuh') ? 'Sepuh' : 'Keroco';
            }

            modalRole.show();
            return;
        }
    });

    // Submit Block Form
    formBlock?.addEventListener('submit', async event => {
        event.preventDefault();
        const submitBtn = document.getElementById('btnSubmitBlock');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';

        try {
            const formData = new FormData(formBlock);
            const payload = Object.fromEntries(formData.entries());
            payload.action = 'block_user';

            await request(`${BASE_URL}app/api/user_management.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            modalBlock.hide();
            notify('Pengguna berhasil diblokir.', 'success');
            await loadUsers();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // Submit Unblock Form
    formUnblock?.addEventListener('submit', async event => {
        event.preventDefault();
        const submitBtn = document.getElementById('btnSubmitUnblock');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';

        try {
            const formData = new FormData(formUnblock);
            const payload = Object.fromEntries(formData.entries());
            payload.action = 'unblock_user';

            await request(`${BASE_URL}app/api/user_management.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            modalUnblock.hide();
            notify('Blokir pengguna berhasil dibuka.', 'success');
            await loadUsers();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // Submit Role Change Form
    formRole?.addEventListener('submit', async event => {
        event.preventDefault();
        const submitBtn = document.getElementById('btnSubmitRole');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';

        try {
            const formData = new FormData(formRole);
            const payload = Object.fromEntries(formData.entries());
            payload.action = 'update_user_role';

            await request(`${BASE_URL}app/api/user_management.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            modalRole.hide();
            notify('Peran pengguna berhasil diperbarui.', 'success');
            await loadUsers();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    loadUsers();
});
