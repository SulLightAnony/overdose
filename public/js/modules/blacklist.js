document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('blacklistTableBody');
    const alertBox = document.getElementById('blacklistAlert');
    const searchInput = document.getElementById('searchBlacklist');
    const roleFilter = document.getElementById('filterBlockedByRole');
    const statusFilter = document.getElementById('filterStatus');
    const sortFilter = document.getElementById('filterSort');
    const resetBtn = document.getElementById('btnResetBlacklistFilter');

    const paginationContainer = document.getElementById('blacklistPaginationContainer');

    const statTotal = document.getElementById('statTotalBlacklist');
    const statHardBlock = document.getElementById('statHardBlock');

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

    function showToast(message, type = 'success') {
        if (window.appToast) {
            window.appToast(message, type);
        } else {
            showAlert(message, type);
        }
    }

    function hardScrollToPagination(container) {
        if (!container) return;
        const targetId = container.id || 'blacklistPaginationContainer';
        window.location.hash = targetId;
        container.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        setTimeout(() => {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }, 150);
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
                        <i class="bi bi-shield-lock-fill me-1"></i>Hard
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
                        <span class="fw-semibold text-dark" style="font-size: 0.85rem;">${email}</span>
                    </td>
                    <td class="py-3">${registeredBadge}</td>
                    <td class="py-3">${blockTypeBadge}</td>
                    <td class="py-3">
                        <div class="text-truncate text-secondary" style="max-width: 250px; font-size: 0.82rem;" title="${reason}">
                            ${reason !== '-' ? reason : '<span class="text-muted fst-italic">Tanpa alasan</span>'}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="fw-medium text-dark small">${adminName}</div>
                    </td>
                    <td class="py-3 text-muted" style="font-size: 0.78rem;">${formatDate(item.createdAt)}</td>
                    <td class="pe-4 py-3 text-end">${actionBtn}</td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pg) {
        if (!paginationContainer) return;

        if (!pg || pg.totalItems === 0) {
            paginationContainer.innerHTML = `<span class="small text-muted">Menampilkan 0 data blacklist</span>`;
            return;
        }

        if (pg.totalPages <= 1) {
            paginationContainer.innerHTML = `<span class="small text-muted">Menampilkan ${pg.totalItems} data blacklist</span>`;
            return;
        }

        let html = `<div class="small text-muted">Halaman ${pg.currentPage} dari ${pg.totalPages} (${pg.totalItems} total blacklist)</div>`;
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

    // Pagination click listener with hard scroll
    if (paginationContainer) {
        paginationContainer.addEventListener('click', async event => {
            const btn = event.target.closest('[data-page]');
            if (!btn || btn.parentElement.classList.contains('disabled')) return;
            const targetPage = parseInt(btn.dataset.page, 10);
            if (targetPage && targetPage > 0 && targetPage !== currentPage) {
                currentPage = targetPage;
                await loadBlacklist();
                hardScrollToPagination(paginationContainer);
            }
        });
    }

    // Add Blacklist Form Submit
    if (formAdd) {
        formAdd.addEventListener('submit', async event => {
            event.preventDefault();
            const emailInput = document.getElementById('blacklistEmail');
            const reasonInput = document.getElementById('blacklistReason');
            const hardBlockInput = formAdd.querySelector('#blCheckHardBlock') || formAdd.querySelector('input[name="isHardBlock"]');

            const email = emailInput ? emailInput.value.trim() : '';
            const reason = reasonInput ? reasonInput.value.trim() : '';
            const isHardBlock = (hardBlockInput && hardBlockInput.type === 'checkbox')
                ? (hardBlockInput.checked ? 1 : 0)
                : Number(hardBlockInput?.value || 0);

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
                        isHardBlock: isHardBlock === 1 ? 1 : 0
                    })
                });

                if (addModal) {
                    addModal.hide();
                }
                formAdd.reset();
                showToast(response.message || 'Alamat email berhasil ditambahkan ke daftar blacklist.', 'success');
                currentPage = 1;
                await loadBlacklist();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                if (btnSubmitAdd) {
                    btnSubmitAdd.disabled = false;
                    btnSubmitAdd.innerHTML = '<i class="bi bi-shield-plus me-1"></i>Blacklist Akun';
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
                showToast(response.message || 'Email berhasil dihapus dari blacklist.', 'success');
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
