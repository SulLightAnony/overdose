document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('blacklistTableBody');
    const alertBox = document.getElementById('blacklistAlert');
    const searchInput = document.getElementById('searchBlacklist');
    const roleFilter = document.getElementById('filterBlockedByRole');
    const statusFilter = document.getElementById('filterStatus');
    const sortFilter = document.getElementById('filterSort');
    const resetBtn = document.getElementById('btnResetBlacklistFilter');

    const paginationContainer = document.getElementById('blacklistPaginationContainer');
    const paginationInfo = document.getElementById('blacklistPaginationInfo');
    const paginationList = document.getElementById('blacklistPaginationList');

    const statTotal = document.getElementById('statTotalBlacklist');
    const statHardBlock = document.getElementById('statHardBlock');
    const statPrimordial = document.getElementById('statPrimordial');
    const statSepuh = document.getElementById('statSepuh');

    const formAdd = document.getElementById('formAddBlacklist');
    const btnSubmitAdd = document.getElementById('btnSubmitBlacklist');

    const confirmModalEl = document.getElementById('confirmDeleteBlacklistModal');
    const deleteEmailText = document.getElementById('deleteBlacklistEmailText');
    const btnConfirmDelete = document.getElementById('btnConfirmDeleteBlacklist');

    let confirmModal = null;
    let addModal = null;

    if (confirmModalEl && typeof bootstrap !== 'undefined') {
        confirmModal = new bootstrap.Modal(confirmModalEl);
    }
    const addModalEl = document.getElementById('addBlacklistModal');
    if (addModalEl && typeof bootstrap !== 'undefined') {
        addModal = new bootstrap.Modal(addModalEl);
    }

    let currentPage = 1;
    let searchTimeout = null;
    let pendingDeleteId = null;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    function showAlert(message, type = 'danger') {
        if (!alertBox) return;
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        alertBox.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show rounded-4 border-0 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi ${icon} fs-5"></i>
                <div class="flex-grow-1">${escapeHtml(message)}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(date.getTime())) return dateStr;
            return new Intl.DateTimeFormat('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        } catch (e) {
            return dateStr;
        }
    }

    async function apiRequest(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            throw new Error('Server mengembalikan respons yang tidak valid.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Permintaan gagal diproses.');
        }

        return data;
    }

    function updateStats(summary) {
        if (!summary) return;
        if (statTotal) statTotal.textContent = Number(summary.totalBlacklist || 0).toLocaleString('id-ID');
        if (statHardBlock) statHardBlock.textContent = Number(summary.totalHardBlock || 0).toLocaleString('id-ID');
        if (statPrimordial) statPrimordial.textContent = Number(summary.primordialCount || 0).toLocaleString('id-ID');
        if (statSepuh) statSepuh.textContent = Number(summary.sepuhCount || 0).toLocaleString('id-ID');
    }

    function renderTable(items) {
        if (!tableBody) return;

        if (!items || items.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-shield-check fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        Tidak ada data blacklist email yang sesuai kriteria.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = items.map(item => {
            const email = escapeHtml(item.emailAddress);
            const reason = escapeHtml(item.reasonDescription || '-');
            const adminName = escapeHtml(item.addedByName || 'Sistem');
            const role = escapeHtml(item.blockedByRole || '-');
            const isHardBlock = Number(item.isHardBlock) === 1;
            const isRegistered = Boolean(item.isRegistered);
            const canDelete = Boolean(item.canDelete);

            // Registered status badge
            let registeredBadge = '';
            if (isRegistered) {
                registeredBadge = `
                    <a href="${BASE_URL}user-detail?id=${encodeURIComponent(item.registeredUserId)}" class="text-decoration-none">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1">
                            <i class="bi bi-person-check-fill me-1"></i>Terdaftar (${escapeHtml(item.registeredUserName || 'User')})
                        </span>
                    </a>
                `;
            } else {
                registeredBadge = `
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3 py-1">
                        <i class="bi bi-person-x me-1"></i>Belum Terdaftar
                    </span>
                `;
            }

            // Block type badge
            let blockTypeBadge = '';
            if (isHardBlock) {
                blockTypeBadge = `
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-semibold shadow-sm">
                        <i class="bi bi-shield-lock-fill me-1"></i>Hard Block
                    </span>
                `;
            } else {
                blockTypeBadge = `
                    <span class="badge bg-secondary bg-opacity-15 text-white rounded-pill px-3 py-1 fw-medium">
                        <i class="bi bi-shield me-1"></i>Biasa
                    </span>
                `;
            }

            // Action button
            let actionBtn = '';
            if (canDelete) {
                actionBtn = `
                    <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold btn-delete-bl" 
                        data-id="${escapeHtml(item.blacklistId)}" 
                        data-email="${email}">
                        <i class="bi bi-unlock-fill me-1"></i>Unblock
                    </button>
                `;
            } else {
                actionBtn = `
                    <span class="badge bg-light text-muted border rounded-pill px-3 py-2 fw-normal" title="Hanya Primordial yang dapat menghapus data ini">
                        <i class="bi bi-lock-fill me-1 text-danger"></i>Terkunci (Primordial)
                    </span>
                `;
            }

            return `
                <tr>
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger flex-shrink-0" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-envelope-slash-fill"></i>
                            </div>
                            <span class="fw-semibold text-dark">${email}</span>
                        </div>
                    </td>
                    <td class="py-3">${registeredBadge}</td>
                    <td class="py-3">${blockTypeBadge}</td>
                    <td class="py-3">
                        <div class="text-truncate" style="max-width: 250px;" title="${reason}">
                            ${reason !== '-' ? reason : '<span class="text-muted fst-italic">Tanpa alasan</span>'}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="fw-medium text-dark small">${adminName}</div>
                    </td>
                    <td class="py-3 small text-muted">${formatDate(item.createdAt)}</td>
                    <td class="pe-4 py-3 text-end">${actionBtn}</td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pagination) {
        if (!paginationContainer || !paginationInfo || !paginationList) return;

        const { totalItems, totalPages, currentPage: activePage, limit } = pagination;

        if (totalItems === 0) {
            paginationInfo.textContent = 'Menampilkan 0 data';
            paginationList.innerHTML = '';
            return;
        }

        const start = ((activePage - 1) * limit) + 1;
        const end = Math.min(activePage * limit, totalItems);
        paginationInfo.textContent = `Menampilkan ${start}-${end} dari ${totalItems} data blacklist`;

        let listHtml = '';

        // Previous button
        listHtml += `
            <li class="page-item ${activePage <= 1 ? 'disabled' : ''}">
                <button class="page-link rounded-pill border-0 px-3 ${activePage <= 1 ? 'text-muted' : 'text-dark'}" data-page="${activePage - 1}">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        const maxButtons = 5;
        let startPage = Math.max(1, activePage - 2);
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let p = startPage; p <= endPage; p++) {
            listHtml += `
                <li class="page-item ${p === activePage ? 'active' : ''}">
                    <button class="page-link rounded-circle border-0 ${p === activePage ? 'bg-dark text-white fw-bold' : 'text-dark'}" data-page="${p}" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                        ${p}
                    </button>
                </li>
            `;
        }

        // Next button
        listHtml += `
            <li class="page-item ${activePage >= totalPages ? 'disabled' : ''}">
                <button class="page-link rounded-pill border-0 px-3 ${activePage >= totalPages ? 'text-muted' : 'text-dark'}" data-page="${activePage + 1}">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;

        paginationList.innerHTML = listHtml;
    }

    async function loadBlacklist() {
        if (!tableBody) return;

        const params = new URLSearchParams({
            action: 'list_blacklist',
            page: currentPage,
            limit: 10,
            search: searchInput ? searchInput.value.trim() : '',
            blockedByRole: roleFilter ? roleFilter.value : 'all',
            status: statusFilter ? statusFilter.value : 'all',
            sort: sortFilter ? sortFilter.value : 'newest'
        });

        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                    Memuat daftar blacklist email...
                </td>
            </tr>
        `;

        try {
            const data = await apiRequest(`${BASE_URL}app/api/blacklist.php?${params.toString()}`);
            renderTable(data.data.blacklist || []);
            renderPagination(data.data.pagination || { totalItems: 0, totalPages: 1, currentPage: 1, limit: 10 });
            updateStats(data.data.summary);
        } catch (error) {
            showAlert(error.message);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger py-4">
                        <i class="bi bi-exclamation-circle me-1"></i> Gagal memuat data: ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    // Filter and search listeners
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadBlacklist();
            }, 350);
        });
    }

    if (roleFilter) {
        roleFilter.addEventListener('change', () => {
            currentPage = 1;
            loadBlacklist();
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            currentPage = 1;
            loadBlacklist();
        });
    }

    if (sortFilter) {
        sortFilter.addEventListener('change', () => {
            currentPage = 1;
            loadBlacklist();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (roleFilter) roleFilter.value = 'all';
            if (statusFilter) statusFilter.value = 'all';
            if (sortFilter) sortFilter.value = 'newest';
            currentPage = 1;
            loadBlacklist();
        });
    }

    // Pagination click listener
    if (paginationList) {
        paginationList.addEventListener('click', event => {
            const btn = event.target.closest('button[data-page]');
            if (!btn) return;
            const targetPage = parseInt(btn.dataset.page, 10);
            if (targetPage && targetPage !== currentPage && targetPage > 0) {
                currentPage = targetPage;
                loadBlacklist();
            }
        });
    }

    // Add Blacklist Form Submit
    if (formAdd) {
        formAdd.addEventListener('submit', async event => {
            event.preventDefault();
            const emailInput = document.getElementById('blacklistEmail');
            const reasonInput = document.getElementById('blacklistReason');
            const isHardBlockInput = formAdd.querySelector('input[name="isHardBlock"]:checked') || formAdd.querySelector('input[name="isHardBlock"]');

            const email = emailInput ? emailInput.value.trim() : '';
            const reason = reasonInput ? reasonInput.value.trim() : '';
            const isHardBlock = isHardBlockInput ? isHardBlockInput.value : '0';

            if (!email) {
                showAlert('Alamat email wajib diisi.', 'warning');
                return;
            }

            if (CURRENT_USER_ROLE === 'Sepuh' && !reason) {
                showAlert('Alasan pemblokiran wajib diisi oleh Sepuh.', 'warning');
                if (reasonInput) reasonInput.focus();
                return;
            }

            if (btnSubmitAdd) {
                btnSubmitAdd.disabled = true;
                btnSubmitAdd.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Menyimpan...';
            }

            try {
                const response = await apiRequest(`${BASE_URL}app/api/blacklist.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_blacklist',
                        emailAddress: email,
                        reasonDescription: reason,
                        isHardBlock: isHardBlock === '1' ? 1 : 0
                    })
                });

                if (addModal) {
                    addModal.hide();
                }
                formAdd.reset();
                showAlert(response.message || 'Email berhasil ditambahkan ke blacklist.', 'success');
                currentPage = 1;
                await loadBlacklist();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                if (btnSubmitAdd) {
                    btnSubmitAdd.disabled = false;
                    btnSubmitAdd.innerHTML = '<i class="bi bi-shield-plus me-1"></i>Simpan Blacklist';
                }
            }
        });
    }

    // Table click listener for Hapus button
    if (tableBody) {
        tableBody.addEventListener('click', event => {
            const btn = event.target.closest('.btn-delete-bl');
            if (!btn) return;

            pendingDeleteId = btn.dataset.id;
            const email = btn.dataset.email || '-';

            if (deleteEmailText) {
                deleteEmailText.textContent = email;
            }

            if (confirmModal) {
                confirmModal.show();
            }
        });
    }

    // Confirm Delete Blacklist
    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            if (!pendingDeleteId) return;

            btnConfirmDelete.disabled = true;
            btnConfirmDelete.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Menghapus...';

            try {
                const response = await apiRequest(`${BASE_URL}app/api/blacklist.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'remove_blacklist',
                        blacklistId: pendingDeleteId
                    })
                });

                if (confirmModal) {
                    confirmModal.hide();
                }
                showAlert(response.message || 'Email berhasil dihapus dari blacklist.', 'success');
                pendingDeleteId = null;
                await loadBlacklist();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                btnConfirmDelete.disabled = false;
                btnConfirmDelete.innerHTML = '<i class="bi bi-unlock-fill me-1"></i>Hapus & Aktifkan';
            }
        });
    }

    // Initial load
    loadBlacklist();
});
