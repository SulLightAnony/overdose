document.addEventListener('DOMContentLoaded', () => {
    const alertBox = document.getElementById('userDetailAlert');
    const profileCard = document.getElementById('profileCard');
    const detailAvatar = document.getElementById('detailAvatar');
    const detailName = document.getElementById('detailName');
    const detailEmail = document.getElementById('detailEmail');
    const detailRoleBadge = document.getElementById('detailRoleBadge');
    const detailStatusBadge = document.getElementById('detailStatusBadge');
    const detailAcademic = document.getElementById('detailAcademic');
    const detailJoined = document.getElementById('detailJoined');
    const userActionControls = document.getElementById('userActionControls');
    const moderationBanner = document.getElementById('moderationBannerContainer');

    // Stats
    const statTotal = document.getElementById('statTotalActivity');
    const statTasksMaterials = document.getElementById('statTasksMaterials');
    const statAnswersComments = document.getElementById('statAnswersComments');
    const statNewsChats = document.getElementById('statNewsChats');

    // Activity Table & Filters
    const activityTableBody = document.getElementById('activityTableBody');
    const searchActivity = document.getElementById('searchActivity');
    const filterType = document.getElementById('filterActivityType');
    const sortActivity = document.getElementById('sortActivity');
    const resetFiltersBtn = document.getElementById('btnResetActivityFilters');
    const paginationContainer = document.getElementById('activityPaginationContainer');

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

    let currentActivityPage = 1;
    let searchDebounceTimer = null;
    let currentUserData = null;

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
                return `<span class="badge bg-danger rounded-pill px-2.5 py-1" style="font-size: 0.72rem;"><i class="bi bi-lock-fill me-1"></i>Hard Block</span>`;
            }
            return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Diblokir</span>`;
        }

        if (lock === 'hard_unblock') {
            return `<span class="badge bg-success text-white rounded-pill px-2.5 py-1" style="font-size: 0.72rem;"><i class="bi bi-shield-lock-fill me-1"></i>Hard Unblock</span>`;
        }

        return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Aktif</span>`;
    }

    const typeConfig = {
        task: { label: 'Tugas', badgeClass: 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', icon: 'bi-journal-check' },
        material: { label: 'Materi', badgeClass: 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', icon: 'bi-file-earmark-text' },
        answer: { label: 'Jawaban Tugas', badgeClass: 'bg-info bg-opacity-10 text-info border border-info border-opacity-25', icon: 'bi-file-earmark-check' },
        comment: { label: 'Komentar Tugas', badgeClass: 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', icon: 'bi-chat-dots' },
        news: { label: 'Berita Kelas', badgeClass: 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50', icon: 'bi-newspaper' },
        global_news: { label: 'Berita Global', badgeClass: 'bg-warning text-dark border border-warning', icon: 'bi-globe' },
        chat: { label: 'Obrolan Global', badgeClass: 'bg-purple bg-opacity-10 text-dark border border-secondary border-opacity-25', icon: 'bi-chat-left-text' },
        bug_report: { label: 'Bug Report', badgeClass: 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', icon: 'bi-bug' }
    };

    function formatDate(rawDate) {
        if (!rawDate) return '-';
        try {
            const date = new Date(rawDate.replace(/-/g, '/'));
            return date.toLocaleString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
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

    async function loadUserDetails() {
        try {
            const result = await request(`${BASE_URL}app/api/user_management.php?action=get_user_detail&id=${TARGET_USER_ID}`);
            const user = result.data?.user;
            if (!user) throw new Error('Pengguna tidak ditemukan.');
            currentUserData = user;

            // Render Profile Card
            detailAvatar.src = user.avatarUrl || defaultAvatar;
            detailName.textContent = user.userName;
            detailEmail.textContent = user.emailAddress;
            detailRoleBadge.innerHTML = renderRoleBadge(user.roleLevel);
            detailStatusBadge.innerHTML = renderStatusBadge(user);

            let classPart = '';
            if (user.majorType && user.classGroup) {
                classPart = `${user.majorType}-${user.classGroup}`;
            } else if (user.classGroup) {
                classPart = user.classGroup;
            } else if (user.majorType) {
                classPart = user.majorType;
            }
            const academicParts = [classPart, user.studyProgram, user.batchYear ? `Angkatan ${user.batchYear}` : ''].filter(Boolean);
            const academicStr = academicParts.length ? academicParts.join(' • ') : (user.majorType || 'Civitas Akademika');
            detailAcademic.innerHTML = `<i class="bi bi-mortarboard me-1"></i>${escapeHtml(academicStr)}`;
            detailJoined.innerHTML = `<i class="bi bi-calendar-event me-1"></i>Bergabung: ${escapeHtml(formatDate(user.createdAt))}`;

            // Render Moderation Info Banner if blocked
            if (user.userStatus === 'blocked') {
                const blockedBy = user.blockedByName ? `${user.blockedByName} (${user.blockedByRole || user.blockedByAdminRole || 'Admin'})` : (user.blockedByRole || 'Sistem');
                const isHard = (user.lockType === 'hard_block' || Number(user.isHardBlock) === 1);
                const blockTypeLabel = isHard ? 'Hard Block (Kunci Primordial)' : 'Blokir Biasa';
                const blockedTime = formatDate(user.blockedAt || user.updatedAt);
                const blockReason = user.blockReason ? user.blockReason : '(Tidak ada keterangan alasan)';

                moderationBanner.className = 'border-top bg-danger bg-opacity-10 p-4';
                moderationBanner.innerHTML = `
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="bi bi-exclamation-diamond-fill fs-5"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                <h6 class="fw-bold text-danger mb-0">Informasi Pemblokiran Akun</h6>
                                <span class="badge ${isHard ? 'bg-dark text-white' : 'bg-danger text-white'} rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                    ${blockTypeLabel}
                                </span>
                            </div>
                            <div class="small text-dark mb-1">
                                <strong>Diblokir oleh:</strong> ${escapeHtml(blockedBy)} &bull; 
                                <strong>Waktu:</strong> ${escapeHtml(blockedTime)}
                            </div>
                            <div class="p-2.5 bg-white border border-danger border-opacity-25 rounded-3 text-secondary small" style="white-space: pre-wrap;">
                                <strong>Alasan:</strong> ${escapeHtml(blockReason)}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                moderationBanner.className = 'd-none';
                moderationBanner.innerHTML = '';
            }

            // Render Action Controls
            let actionHtml = '';

            if (user.canChangeRole) {
                actionHtml += `
                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" id="btnOpenRoleModal">
                        <i class="bi bi-person-gear me-1"></i>Ubah Peran
                    </button>
                `;
            }

            if (user.canToggleHardUnblock) {
                const isHardUnblock = (user.lockType === 'hard_unblock');
                actionHtml += `
                    <button type="button" class="btn ${isHardUnblock ? 'btn-outline-secondary' : 'btn-outline-success'} rounded-pill btn-sm px-3 fw-semibold" id="btnToggleHardUnblock">
                        <i class="bi ${isHardUnblock ? 'bi-shield-slash' : 'bi-shield-lock-fill'} me-1"></i>
                        ${isHardUnblock ? 'Cabut Hard Unblock' : 'Aktifkan Hard Unblock'}
                    </button>
                `;
            }

            if (user.canBlock) {
                actionHtml += `
                    <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold" id="btnOpenBlockModal">
                        <i class="bi bi-slash-circle me-1"></i>Blokir Pengguna
                    </button>
                `;
            }

            if (user.canUnblock) {
                actionHtml += `
                    <button type="button" class="btn btn-outline-success rounded-pill btn-sm px-3 fw-semibold" id="btnOpenUnblockModal">
                        <i class="bi bi-check-circle me-1"></i>Buka Blokir
                    </button>
                `;
            }

            userActionControls.innerHTML = actionHtml;

        } catch (error) {
            notify(error.message, 'danger');
        }
    }

    async function loadActivities() {
        if (!activityTableBody) return;

        activityTableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                    Memuat riwayat aktivitas...
                </td>
            </tr>`;

        const search = searchActivity?.value.trim() || '';
        const type = filterType?.value || 'all';
        const sort = sortActivity?.value || 'newest';

        const params = new URLSearchParams({
            userId: TARGET_USER_ID,
            search: search,
            type: type,
            sort: sort,
            page: currentActivityPage,
            limit: 10
        });

        try {
            const result = await request(`${BASE_URL}app/api/user_activities.php?${params.toString()}`);
            const activities = result.data?.activities || [];
            const stats = result.data?.stats || {};
            const pagination = result.data?.pagination || { totalItems: 0, totalPages: 1, currentPage: 1, limit: 10 };

            // Update Stats Chips
            if (statTotal) statTotal.textContent = stats.totalActivities || 0;
            if (statTasksMaterials) statTasksMaterials.textContent = (Number(stats.totalTasks || 0) + Number(stats.totalMaterials || 0));
            if (statAnswersComments) statAnswersComments.textContent = (Number(stats.totalAnswers || 0) + Number(stats.totalComments || 0));
            if (statNewsChats) statNewsChats.textContent = (Number(stats.totalNews || 0) + Number(stats.totalGlobalNews || 0) + Number(stats.totalChats || 0));

            if (!activities.length) {
                activityTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-journal-x fs-2 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-semibold text-dark mb-1">Belum Ada Aktivitas</h6>
                            <p class="small text-muted mb-0">Tidak ada riwayat aktivitas yang cocok dengan filter yang dipilih.</p>
                        </td>
                    </tr>`;
                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            activityTableBody.innerHTML = activities.map(act => {
                const conf = typeConfig[act.activityType] || { label: act.activityType, badgeClass: 'bg-light text-dark', icon: 'bi-dot' };
                const dateStr = formatDate(act.activityDate);
                const titleStr = act.itemTitle || '-';
                const snippetStr = act.itemSnippet || '';
                const targetUrl = act.targetUrl ? `${BASE_URL}${act.targetUrl}` : '#';

                return `
                    <tr class="activity-row" style="cursor: pointer;" onclick="window.location.href='${escapeHtml(targetUrl)}';">
                        <td class="ps-4 py-3">
                            <div class="fw-bold text-dark text-break" style="font-size: 0.88rem;">${escapeHtml(titleStr)}</div>
                            ${snippetStr ? `<div class="text-secondary small text-break text-truncate" style="max-width: 520px; font-size: 0.78rem;">${escapeHtml(snippetStr)}</div>` : ''}
                        </td>
                        <td>
                            <span class="badge rounded-pill px-3 py-1.5 fw-semibold ${conf.badgeClass}" style="font-size: 0.75rem;">
                                <i class="bi ${conf.icon} me-1"></i>${conf.label}
                            </span>
                        </td>
                        <td class="text-muted small">
                            <i class="bi bi-clock me-1"></i>${escapeHtml(dateStr)}
                        </td>
                        <td class="text-end pe-4" onclick="event.stopPropagation();">
                            <a href="${escapeHtml(targetUrl)}" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold" style="font-size: 0.75rem;" title="Buka Detail">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Buka
                            </a>
                        </td>
                    </tr>
                `;
            }).join('');

            renderPagination(pagination);

        } catch (error) {
            activityTableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4"><div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div></td></tr>`;
        }
    }

    function renderPagination(pg) {
        if (!paginationContainer) return;
        if (pg.totalPages <= 1) {
            paginationContainer.innerHTML = `<span class="small text-muted">Menampilkan ${pg.totalItems} aktivitas</span>`;
            return;
        }

        let html = `<div class="small text-muted">Halaman ${pg.currentPage} dari ${pg.totalPages} (${pg.totalItems} total aktivitas)</div>`;
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

    function hardScrollToPagination(container) {
        if (!container) return;
        const targetId = container.id || 'activityPaginationContainer';
        window.location.hash = targetId;
        container.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        setTimeout(() => {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }, 150);
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', async e => {
            const btn = e.target.closest('[data-page]');
            if (!btn || btn.parentElement.classList.contains('disabled')) return;
            const targetPage = Number(btn.dataset.page);
            if (targetPage && targetPage > 0 && targetPage !== currentActivityPage) {
                currentActivityPage = targetPage;
                await loadActivities();
                hardScrollToPagination(paginationContainer);
            }
        });
    }

    if (searchActivity) {
        searchActivity.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                currentActivityPage = 1;
                loadActivities();
            }, 300);
        });
    }

    if (filterType) {
        filterType.addEventListener('change', () => {
            currentActivityPage = 1;
            loadActivities();
        });
    }

    if (sortActivity) {
        sortActivity.addEventListener('change', () => {
            currentActivityPage = 1;
            loadActivities();
        });
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            if (searchActivity) searchActivity.value = '';
            if (filterType) filterType.value = 'all';
            if (sortActivity) sortActivity.value = 'newest';
            currentActivityPage = 1;
            loadActivities();
        });
    }

    // Handle Top Action Button Clicks
    userActionControls.addEventListener('click', async event => {
        // Block button
        if (event.target.closest('#btnOpenBlockModal') && modalBlock && currentUserData) {
            document.getElementById('blockReasonDescription').value = '';
            const normalRadio = document.getElementById('blockTypeNormal');
            if (normalRadio) normalRadio.checked = true;
            modalBlock.show();
            return;
        }

        // Unblock button
        if (event.target.closest('#btnOpenUnblockModal') && modalUnblock && currentUserData) {
            const normalRadio = document.getElementById('unblockTypeNormal');
            if (normalRadio) normalRadio.checked = true;
            modalUnblock.show();
            return;
        }

        // Role button
        if (event.target.closest('#btnOpenRoleModal') && modalRole && currentUserData) {
            const selectRole = document.getElementById('selectNewRole');
            if (selectRole) {
                selectRole.value = (currentUserData.roleLevel === 'Sepuh') ? 'Sepuh' : 'Keroco';
            }
            modalRole.show();
            return;
        }

        // Toggle Hard Unblock
        const toggleBtn = event.target.closest('#btnToggleHardUnblock');
        if (toggleBtn && currentUserData) {
            const isCurrentlyHard = (currentUserData.lockType === 'hard_unblock');
            const targetLock = isCurrentlyHard ? 'none' : 'hard_unblock';
            const actionMsg = isCurrentlyHard ? 'Cabut proteksi Hard Unblock dari pengguna ini?' : 'Aktifkan proteksi Hard Unblock? Pengguna ini tidak akan bisa diblokir oleh Sepuh.';
            const confirmed = window.appConfirm ? await window.appConfirm(actionMsg) : confirm(actionMsg);
            if (!confirmed) return;

            toggleBtn.disabled = true;

            try {
                await request(`${BASE_URL}app/api/user_management.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'set_hard_unblock', targetUserId: TARGET_USER_ID, lockType: targetLock })
                });
                notify('Status proteksi berhasil diperbarui.', 'success');
                await loadUserDetails();
            } catch (error) {
                notify(error.message, 'danger');
                toggleBtn.disabled = false;
            }
        }
    });

    // Form Submissions
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
            await loadUserDetails();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

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
            await loadUserDetails();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

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
            await loadUserDetails();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    loadUserDetails();
    loadActivities();
});
