document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bugReportForm');
    const submitBtn = document.getElementById('submitBugReport');
    const list = document.getElementById('bugReportsList');
    const alertBox = document.getElementById('bugReportAlert');
    const searchInput = document.getElementById('searchBugReport');
    const filterStatus = document.getElementById('filterStatusBugReport');
    const sortSelect = document.getElementById('sortBugReport');
    const paginationContainer = document.getElementById('bugReportPaginationContainer');
    const badgeReportCount = document.getElementById('badgeReportCount');
    const tabsContainer = document.getElementById('bugReportTabs');
    const reportListTab = document.getElementById('report-list-tab');

    let currentPage = 1;
    let searchDebounceTimer = null;
    let cooldownInterval = null;
    let currentCooldown = typeof INITIAL_COOLDOWN_SECONDS !== 'undefined' ? Number(INITIAL_COOLDOWN_SECONDS) : 0;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    const notify = (message, type = 'success') => {
        if (window.appToast) {
            window.appToast(message, type);
        } else if (alertBox) {
            alertBox.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show rounded-3 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5"></i>
                    <div class="flex-grow-1">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    // -------------------------------------------------------------------------
    // Cooldown Timer Functionality (1 Jam)
    // -------------------------------------------------------------------------
    function formatCooldownTime(totalSeconds) {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    function startCooldown(seconds) {
        clearInterval(cooldownInterval);
        currentCooldown = seconds;

        if (!submitBtn) return;

        if (currentCooldown <= 0) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send"></i> Kirim Laporan';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>Tunggu ${formatCooldownTime(currentCooldown)}`;

        cooldownInterval = setInterval(() => {
            currentCooldown -= 1;
            if (currentCooldown <= 0) {
                clearInterval(cooldownInterval);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send"></i> Kirim Laporan';
            } else {
                submitBtn.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>Tunggu ${formatCooldownTime(currentCooldown)}`;
            }
        }, 1000);
    }

    if (currentCooldown > 0) {
        startCooldown(currentCooldown);
    }

    // -------------------------------------------------------------------------
    // Tab Persistence on Refresh
    // -------------------------------------------------------------------------
    if (CAN_REVIEW_BUG_REPORTS && reportListTab) {
        const savedTab = localStorage.getItem('activeBugReportTab');
        if (savedTab === 'report-list') {
            const tabInstance = bootstrap.Tab.getOrCreateInstance(reportListTab);
            tabInstance.show();
        }

        if (tabsContainer) {
            tabsContainer.addEventListener('shown.bs.tab', event => {
                if (event.target.id === 'report-list-tab') {
                    localStorage.setItem('activeBugReportTab', 'report-list');
                } else {
                    localStorage.removeItem('activeBugReportTab');
                }
            });
        }
    }

    function renderRoleBadge(role) {
        if (!role) return '';
        const lower = role.toLowerCase();
        if (lower === 'primordial') {
            return `<span class="badge" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.65rem; font-weight: 600;">Primordial</span>`;
        }
        if (lower === 'sepuh') {
            return `<span class="badge bg-secondary text-white" style="font-size: 0.65rem;">Sepuh</span>`;
        }
        return `<span class="text-muted" style="font-size: 0.72rem;">Keroco</span>`;
    }

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
        if (!response.ok || !data.success) {
            const err = new Error(data.message || 'Permintaan gagal diproses.');
            err.cooldownSeconds = data.cooldownSeconds;
            throw err;
        }
        return data;
    }

    // -------------------------------------------------------------------------
    // Load Reports List
    // -------------------------------------------------------------------------
    async function loadReports() {
        if (!list || !CAN_REVIEW_BUG_REPORTS) return;

        list.innerHTML = `
            <div class="text-center text-muted py-5">
                <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                Memuat daftar laporan...
            </div>`;

        const search = searchInput?.value.trim() || '';
        const status = filterStatus?.value || 'all';
        const sort = sortSelect?.value || 'newest';

        const params = new URLSearchParams({
            action: 'list',
            search: search,
            status: status,
            sort: sort,
            page: currentPage,
            limit: 10
        });

        try {
            const result = await request(`${BASE_URL}app/api/bug_reports.php?${params.toString()}`);
            const reports = result.data?.reports || [];
            const pagination = result.data?.pagination || { totalItems: 0, totalPages: 1, currentPage: 1, limit: 10 };
            const stats = result.data?.stats || {};

            // Notifikasi lingkaran di tab: hanya yang Diproses (bukan Selesai)
            // Jika 0, sembunyikan badge.
            const inProgressCount = Number(stats.totalInProgress || 0);
            if (badgeReportCount) {
                if (inProgressCount > 0) {
                    badgeReportCount.textContent = inProgressCount;
                    badgeReportCount.style.display = 'inline-block';
                } else {
                    badgeReportCount.style.display = 'none';
                }
            }

            if (!reports.length) {
                list.innerHTML = `
                    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <h6 class="fw-semibold text-dark mb-1">Tidak Ada Laporan Ditemukan</h6>
                            <p class="small text-muted mb-0">Belum ada laporan yang sesuai dengan kriteria pencarian atau filter yang dipilih.</p>
                        </div>
                    </div>`;
                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            list.innerHTML = reports.map(report => {
                const authorName = report.userName || 'Pengguna Nonaktif';
                const authorRole = report.roleLevel || 'Keroco';
                const avatar = report.avatarUrl || `${BASE_URL}public/assets/img/logo.png`;
                const dateStr = formatDate(report.created_at);
                const isResolved = (report.status === 'resolved' || report.status === 'closed');

                // Tombol status: "Selesai" jika masih diproses, "Batal Selesai" jika sudah selesai
                const statusButton = isResolved
                    ? `<button class="btn btn-outline-secondary rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 btn-toggle-status" data-id="${Number(report.id)}" data-target="in_progress" style="font-size: 0.75rem;">
                           <i class="bi bi-arrow-counterclockwise"></i>Batal Selesai
                       </button>`
                    : `<button class="btn btn-outline-success rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 btn-toggle-status" data-id="${Number(report.id)}" data-target="resolved" style="font-size: 0.75rem;">
                           <i class="bi bi-check-circle"></i>Selesai
                       </button>`;

                return `
                    <article class="card border-0 shadow-sm rounded-4 overflow-hidden" id="report-card-${Number(report.id)}">
                        <div class="card-body p-3 p-md-4">
                            <!-- Header Bar: Profil & Aksi Status -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom border-light">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="${escapeHtml(avatar)}" alt="${escapeHtml(authorName)}" class="rounded-circle border" style="width: 36px; height: 36px; object-fit: cover;" referrerpolicy="no-referrer" onerror="this.src='${BASE_URL}public/assets/img/logo.png'">
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">${escapeHtml(authorName)}</div>
                                        <div class="d-flex align-items-center gap-2">
                                            ${renderRoleBadge(authorRole)}
                                            <span class="text-muted" style="font-size: 0.72rem;">
                                                <i class="bi bi-clock me-1"></i>${escapeHtml(dateStr)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    ${statusButton}
                                    <button class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1 btn-delete-report" type="button" data-id="${Number(report.id)}" style="font-size: 0.75rem;">
                                        <i class="bi bi-trash3"></i>Hapus
                                    </button>
                                </div>
                            </div>
                            <!-- Deskripsi Laporan (Gap lebih ringkas) -->
                            <div class="text-secondary small text-break mt-2" style="white-space: pre-wrap; line-height: 1.6;">${escapeHtml(report.description)}</div>
                        </div>
                    </article>
                `;
            }).join('');

            renderPagination(pagination);

        } catch (error) {
            list.innerHTML = `<div class="alert alert-danger rounded-3">${escapeHtml(error.message)}</div>`;
        }
    }

    function renderPagination(pg) {
        if (!paginationContainer) return;
        if (pg.totalPages <= 1) {
            paginationContainer.innerHTML = `<span class="small text-muted">Menampilkan ${pg.totalItems} laporan</span>`;
            return;
        }

        let html = `<div class="small text-muted">Halaman ${pg.currentPage} dari ${pg.totalPages} (${pg.totalItems} total laporan)</div>`;
        html += `<ul class="pagination pagination-sm mb-0">`;

        // Prev
        html += `<li class="page-item ${pg.currentPage <= 1 ? 'disabled' : ''}">
            <button class="page-link rounded-pill px-3 me-1" data-page="${pg.currentPage - 1}">Sebelumnya</button>
        </li>`;

        // Page buttons
        const start = Math.max(1, pg.currentPage - 2);
        const end = Math.min(pg.totalPages, pg.currentPage + 2);

        for (let p = start; p <= end; p++) {
            html += `<li class="page-item ${p === pg.currentPage ? 'active' : ''}">
                <button class="page-link rounded-circle mx-0.5 text-center" style="width: 32px; height: 32px; padding: 0; line-height: 32px;" data-page="${p}">${p}</button>
            </li>`;
        }

        // Next
        html += `<li class="page-item ${pg.currentPage >= pg.totalPages ? 'disabled' : ''}">
            <button class="page-link rounded-pill px-3 ms-1" data-page="${pg.currentPage + 1}">Selanjutnya</button>
        </li>`;

        html += `</ul>`;
        paginationContainer.innerHTML = html;
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', e => {
            const btn = e.target.closest('[data-page]');
            if (!btn || btn.parentElement.classList.contains('disabled')) return;
            currentPage = Number(btn.dataset.page);
            loadReports();
        });
    }

    // Filter & Search event listeners
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                currentPage = 1;
                loadReports();
            }, 300);
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            currentPage = 1;
            loadReports();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            currentPage = 1;
            loadReports();
        });
    }

    // -------------------------------------------------------------------------
    // Submit Laporan Form Handler
    // -------------------------------------------------------------------------
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (currentCooldown > 0) {
            notify(`Anda masih dalam masa jeda pengiriman. Silakan tunggu ${formatCooldownTime(currentCooldown)}.`, 'danger');
            return;
        }

        const button = document.getElementById('submitBugReport');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Mengirim...';

        try {
            const data = new FormData(form);
            data.append('action', 'create');
            const res = await request(`${BASE_URL}app/api/bug_reports.php`, { method: 'POST', body: data });
            form.reset();
            notify(res.message || 'Laporan berhasil dikirim. Terima kasih!', 'success');
            
            // Aktifkan cooldown sesuai respons server (0 untuk Primordial, 60 untuk Sepuh, 3600 untuk Keroco)
            const cooldown = Number(res.cooldownSeconds ?? 0);
            startCooldown(cooldown);

            if (CAN_REVIEW_BUG_REPORTS) {
                await loadReports();
            }
        } catch (error) {
            notify(error.message, 'danger');
            if (error.cooldownSeconds && error.cooldownSeconds > 0) {
                startCooldown(error.cooldownSeconds);
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    });

    // -------------------------------------------------------------------------
    // Toggle Status (Selesai / Batal Selesai) & Delete Click Handler
    // -------------------------------------------------------------------------
    list?.addEventListener('click', async event => {
        // Toggle Status Button
        const toggleBtn = event.target.closest('.btn-toggle-status');
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            const targetStatus = toggleBtn.dataset.target;
            toggleBtn.disabled = true;

            try {
                const res = await request(`${BASE_URL}app/api/bug_reports.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', id: id, status: targetStatus })
                });
                notify(res.message || 'Status laporan berhasil diperbarui.', 'success');
                await loadReports();
            } catch (error) {
                notify(error.message, 'danger');
                toggleBtn.disabled = false;
            }
            return;
        }

        // Delete Report Button
        const deleteBtn = event.target.closest('.btn-delete-report');
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const confirmed = window.appConfirm 
                ? await window.appConfirm('Apakah Anda yakin ingin menghapus laporan bug ini?') 
                : confirm('Hapus laporan bug ini?');
            if (!confirmed) return;

            deleteBtn.disabled = true;

            try {
                await request(`${BASE_URL}app/api/bug_reports.php`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                notify('Laporan berhasil dihapus.', 'success');
                await loadReports();
            } catch (error) {
                notify(error.message, 'danger');
                deleteBtn.disabled = false;
            }
        }
    });

    // Inisialisasi daftar laporan jika reviewer
    if (CAN_REVIEW_BUG_REPORTS) {
        loadReports();
    }
});
