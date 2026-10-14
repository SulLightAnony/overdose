/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Task Detail
 * FILE LOCATION: public/js/modules/task_detail.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini mengontrol seluruh logika interaksi dinamik dan komunikasi AJAX
 * pada Halaman Detail Tugas (app/views/task_detail.php). Meliputi pemuatan detail tugas,
 * penanganan status Soft-Delete, render multi-file lampiran grid warna-warni, tombol
 * "Tandai Selesai" dengan efek invert, daftar mahasiswa selesai dengan proteksi privasi,
 * alur modal terpadu sharing jawaban (unggah, lihat, dan inline edit dengan dirty tracking),
 * serta pengelolaan komentar dengan form bergaya Global Chat (edit & hapus komentar).
 * 
 * CATATAN INTEGRITAS:
 * - DILARANG menggunakan emoji di dalam baris kode PHP, JS, HTML, maupun CSS.
 * - Tetap menggunakan tabel task_shared_answers dan task_shared_answer_files di database.
 */

document.addEventListener('DOMContentLoaded', function () {

    // Elemen DOM Kontainer
    const skeletonLoader = document.getElementById('skeleton-loader');
    const mainContentWrapper = document.getElementById('main-content-wrapper');
    const alertContainer = document.getElementById('alert-container');
    const backToCourseBtn = document.getElementById('backToCourseBtn');

    // Elemen Banner Informasi Tugas
    const taskBannerCard = document.getElementById('taskBannerCard');
    const taskBannerIcon = document.getElementById('taskBannerIcon');
    const courseNameTitle = document.getElementById('courseNameTitle');
    const taskTitle = document.getElementById('taskTitle');
    const taskTypeBadge = document.getElementById('taskTypeBadge');
    const taskStatusBadgeContainer = document.getElementById('taskStatusBadgeContainer');
    const taskDeadlineText = document.getElementById('taskDeadlineText');
    const bannerAuthorInfo = document.getElementById('bannerAuthorInfo');
    const btnDropdownEditTask = document.getElementById('btnDropdownEditTask');
    const btnDropdownDeleteTask = document.getElementById('btnDropdownDeleteTask');

    // Elemen Deskripsi & File Grid
    const taskDescription = document.getElementById('taskDescription');
    const taskFilesGrid = document.getElementById('taskFilesGrid');
    const noFilesText = document.getElementById('noFilesText');

    // Elemen Aksi "Tandai Selesai"
    const btnToggleComplete = document.getElementById('btnToggleComplete');
    const completeBtnText = document.getElementById('completeBtnText');

    // Elemen Mahasiswa yang Telah Selesai & Sharing Jawaban
    const completionCountBadge = document.getElementById('completionCountBadge');
    const completionsList = document.getElementById('completionsList');
    const btnShareAnswer = document.getElementById('btnShareAnswer');

    // Elemen Komentar (Gaya Global Chat)
    const commentsContainer = document.getElementById('commentsContainer');
    const commentForm = document.getElementById('commentForm');
    const commentContent = document.getElementById('commentContent');
    const btnSubmitComment = document.getElementById('btnSubmitComment');
    const btnSubmitCommentIcon = document.getElementById('btnSubmitCommentIcon');
    const btnCancelEditComment = document.getElementById('btnCancelEditComment');

    // Elemen Modal Edit Tugas
    const formEditTask = document.getElementById('formEditTask');
    const editTaskExistingFiles = document.getElementById('editTaskExistingFiles');
    const editTaskAttachments = document.getElementById('editTaskAttachments');
    const btnSubmitEditTask = document.getElementById('btnSubmitEditTask');

    // Elemen Modal Bagikan Jawaban Baru
    const formShareAnswer = document.getElementById('formShareAnswer');
    const btnSubmitShareAnswer = document.getElementById('btnSubmitShareAnswer');

    // Elemen Modal Lihat / Edit Jawaban
    const viewAnswerModalEl = document.getElementById('viewAnswerModal');
    const modalAnswerAuthorAvatar = document.getElementById('modalAnswerAuthorAvatar');
    const modalAnswerAuthorName = document.getElementById('modalAnswerAuthorName');
    const modalAnswerDateMeta = document.getElementById('modalAnswerDateMeta');
    const btnCopyAnswerLink = document.getElementById('btnCopyAnswerLink');
    const btnDeleteOwnAnswer = document.getElementById('btnDeleteOwnAnswer');
    const readOnlyAnswerContent = document.getElementById('readOnlyAnswerContent');
    const readOnlyAnswerTitle = document.getElementById('readOnlyAnswerTitle');
    const readOnlyAnswerNotes = document.getElementById('readOnlyAnswerNotes');
    const readOnlyAnswerFilesGrid = document.getElementById('readOnlyAnswerFilesGrid');
    const noAnswerFilesText = document.getElementById('noAnswerFilesText');
    const readOnlyAnswerFooter = document.getElementById('readOnlyAnswerFooter');

    // Elemen Form Edit Jawaban Inline
    const formEditAnswer = document.getElementById('formEditAnswer');
    const editAnswerIdHidden = document.getElementById('editAnswerIdHidden');
    const editAnswerTitleInput = document.getElementById('editAnswerTitleInput');
    const editAnswerNotesInput = document.getElementById('editAnswerNotesInput');
    const editAnswerExistingFiles = document.getElementById('editAnswerExistingFiles');
    const editAnswerNewAttachments = document.getElementById('editAnswerNewAttachments');
    const btnSaveAnswerEdit = document.getElementById('btnSaveAnswerEdit');

    // State Internal Modul
    let isTaskCompletedByUser = false;
    let mySharedAnswerId = null;
    let currentlyViewingAnswerId = null;
    let initialAnswerTitle = '';
    let initialAnswerNotes = '';
    let editingCommentId = null;

    // ====================================================================================
    // 1. UTILITAS FORMATTER & HELPER
    // ====================================================================================

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatIndoDate(dateString) {
        if (!dateString) return '-';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const date = new Date(dateString.replace(/-/g, '/'));
        if (isNaN(date.getTime())) return dateString;
        const d = date.getDate();
        const m = months[date.getMonth()];
        const y = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, '0');
        const mm = String(date.getMinutes()).padStart(2, '0');
        return `${d} ${m} ${y}, ${hh}.${mm}`;
    }

    function formatFileSize(bytes) {
        const size = Number(bytes || 0);
        if (size <= 0) return '0 KB';
        if (size < 1024 * 1024) {
            return `${(size / 1024).toFixed(1)} KB`;
        }
        return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    }

    function truncateUsername(name, maxLen = 20) {
        if (!name) return 'Pengguna';
        return name.length > maxLen ? name.substring(0, maxLen) + '...' : name;
    }

    function resolveAssetUrl(path) {
        if (!path) return `${BASE_URL}public/assets/img/logo.png`;
        return /^(https?:)?\/\//i.test(path) ? path : `${BASE_URL}${path}`;
    }

    function roleBadgeCompactHtml(role) {
        if (role === 'Primordial') {
            return '<span class="badge" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.6rem; font-weight: 600; padding: 2px 5px;">Primordial</span>';
        }
        if (role === 'Sepuh') {
            return '<span class="badge bg-secondary text-white" style="font-size: 0.6rem; padding: 2px 5px;">Sepuh</span>';
        }
        return '<span class="text-muted" style="font-size: 0.65rem;">Keroco</span>';
    }

    function getFileIconMeta(fileName) {
        const ext = (fileName || '').split('.').pop().toLowerCase();
        switch (ext) {
            case 'pdf':
                return { icon: 'bi-file-earmark-pdf-fill', color: '#dc3545', label: 'PDF' };
            case 'doc':
            case 'docx':
                return { icon: 'bi-file-earmark-word-fill', color: '#0d6efd', label: 'Word' };
            case 'xls':
            case 'xlsx':
                return { icon: 'bi-file-earmark-excel-fill', color: '#198754', label: 'Excel' };
            case 'ppt':
            case 'pptx':
                return { icon: 'bi-file-earmark-ppt-fill', color: '#fd7e14', label: 'PowerPoint' };
            case 'zip':
            case 'rar':
            case '7z':
            case 'tar':
            case 'gz':
                return { icon: 'bi-file-earmark-zip-fill', color: '#ffc107', label: 'Arsip' };
            default:
                return { icon: 'bi-file-earmark-text-fill', color: '#6c757d', label: 'Dokumen' };
        }
    }

    function linkifyDescription(value) {
        const pattern = /https?:\/\/[^\s<]+/gi;
        let html = '';
        let lastIndex = 0;
        for (const match of value.matchAll(pattern)) {
            html += escapeHtml(value.slice(lastIndex, match.index));
            const url = match[0];
            const safeUrl = escapeHtml(url);
            html += `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${safeUrl}</a>`;
            lastIndex = match.index + url.length;
        }
        return html + escapeHtml(value.slice(lastIndex));
    }

    function formatRemainingCountdown(diffMs) {
        if (diffMs <= 0) return 'Tenggat terlewat';
        const totalMinutes = Math.floor(diffMs / (1000 * 60));
        const totalHours = Math.floor(totalMinutes / 60);
        const days = Math.floor(totalHours / 24);
        const hours = totalHours % 24;
        const minutes = totalMinutes % 60;

        if (days > 0) return `Sisa ${days} hari ${hours} jam`;
        if (hours > 0) return `Sisa ${hours} jam ${minutes} menit`;
        return `Sisa ${minutes} menit`;
    }

    // ====================================================================================
    // 2. PEMUATAN DETAIL TUGAS UTAMA
    // ====================================================================================

    function loadTaskDetail() {
        fetch(`${BASE_URL}app/api/tasks.php?action=detail&taskId=${CURRENT_TASK_ID}`)
            .then(response => response.json())
            .then(res => {
                skeletonLoader.classList.add('d-none');

                if (!res.success || !res.data) {
                    alertContainer.innerHTML = `<div class="alert alert-danger fw-bold rounded-4">Gagal memuat detail tugas: ${escapeHtml(res.message || 'Data tidak ditemukan.')}</div>`;
                    return;
                }

                const task = res.data.task;
                const files = res.data.files || [];
                const completions = res.data.completions || [];
                const comments = res.data.comments || [];
                const canEdit = res.data.canEdit;
                isTaskCompletedByUser = Boolean(res.data.isCompleted);
                mySharedAnswerId = res.data.mySharedAnswerId ? Number(res.data.mySharedAnswerId) : null;

                // Penanganan Tombol Back Kontekstual
                const urlParams = new URLSearchParams(window.location.search);
                const navFrom = urlParams.get('from');
                const navFilter = urlParams.get('filter') || 'all';
                const navSearch = urlParams.get('search') || '';

                let referrerIsGlobalList = false;
                if (!navFrom && document.referrer) {
                    try {
                        referrerIsGlobalList = new URL(document.referrer).pathname.endsWith('/tasks');
                    } catch (error) {
                        referrerIsGlobalList = false;
                    }
                }
                const cameFromGlobalList = navFrom === 'global' || referrerIsGlobalList;
                const cameFromDashboard = navFrom === 'dashboard';

                if (backToCourseBtn) {
                    if (cameFromDashboard) {
                        backToCourseBtn.href = `${BASE_URL}dashboard`;
                    } else if (cameFromGlobalList) {
                        let backUrl = `${BASE_URL}tasks?filter=${encodeURIComponent(navFilter)}`;
                        if (navSearch) backUrl += `&search=${encodeURIComponent(navSearch)}`;
                        backToCourseBtn.href = backUrl;
                    } else {
                        backToCourseBtn.href = `${BASE_URL}course-detail?id=${task.courseId}&tab=tasks`;
                        backToCourseBtn.onclick = function () {
                            sessionStorage.setItem(`course_restore_scroll_${task.courseId}`, '1');
                        };
                    }
                }

                // Penanganan Soft-Delete Item
                if (parseInt(task.deletionStatus) === 1) {
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger border-danger shadow-sm rounded-4">
                            <h5 class="alert-heading fw-bold">Akses Diblokir</h5>
                            <p class="mb-0">Tugas ini telah dihapus oleh <strong>${escapeHtml(task.deletedByUserName || 'Sistem')}</strong> pada <strong>${formatIndoDate(task.deletedAt)}</strong>.</p>
                        </div>
                    `;
                    return;
                }

                mainContentWrapper.classList.remove('d-none');

                // Render Banner Card Informasi Tugas
                courseNameTitle.textContent = task.courseTitle ? `${task.courseCode || ''} - ${task.courseTitle}` : (task.courseCode || '');
                taskTitle.textContent = task.taskTitle;
                
                if (task.taskType && task.taskType.trim().toLowerCase() !== 'tugas') {
                    taskTypeBadge.textContent = task.taskType;
                    taskTypeBadge.classList.remove('d-none');
                } else {
                    taskTypeBadge.classList.add('d-none');
                }

                // Status Waktu & Deadline
                const now = new Date();
                const dueDate = task.dueDate ? new Date(task.dueDate.replace(/-/g, '/')) : null;
                const isMissed = !isTaskCompletedByUser && dueDate && (dueDate < now);

                let statusBadgeHtml = '';
                let borderLeftColor = '#ffc107';

                if (isTaskCompletedByUser) {
                    statusBadgeHtml = '<span class="badge bg-success text-white rounded-pill px-3 py-1 fw-medium align-middle"><i class="bi bi-check2-circle me-1"></i>Selesai</span>';
                    borderLeftColor = '#198754';
                } else if (isMissed) {
                    statusBadgeHtml = '<span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold align-middle shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Terlewat</span>';
                    borderLeftColor = '#dc3545';
                } else {
                    statusBadgeHtml = '<span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-medium align-middle"><i class="bi bi-clock-history me-1"></i>Pending</span>';
                    borderLeftColor = '#ffc107';
                }

                taskStatusBadgeContainer.innerHTML = statusBadgeHtml;
                taskBannerCard.style.setProperty('border-left', `5px solid ${borderLeftColor}`, 'important');

                let deadlineDisplay = `Tenggat: ${formatIndoDate(task.dueDate)}`;
                if (dueDate && !isTaskCompletedByUser && !isMissed) {
                    const diffMs = dueDate.getTime() - now.getTime();
                    deadlineDisplay += ` <span class="text-secondary fw-normal">(${formatRemainingCountdown(diffMs)})</span>`;
                }
                taskDeadlineText.innerHTML = deadlineDisplay;

                // Informasi Pembuat di Bagian Bawah Banner
                bannerAuthorInfo.innerHTML = `Ditambahkan oleh ${escapeHtml(task.authorName || 'Pengguna Nonaktif')} pada ${formatIndoDate(task.createdAt)}`;

                // Pengaturan Tombol Edit & Hapus Banner
                if (canEdit) {
                    if (btnDropdownEditTask) btnDropdownEditTask.classList.remove('d-none');
                    if (btnDropdownDeleteTask) btnDropdownDeleteTask.classList.remove('d-none');

                    document.getElementById('editCourseId').value = task.courseId;
                    document.getElementById('editTaskTitle').value = task.taskTitle;
                    if (task.dueDate) {
                        const localDate = new Date(task.dueDate);
                        localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
                        document.getElementById('editDueDate').value = localDate.toISOString().slice(0, 16);
                    }
                    document.getElementById('editTaskDescription').value = task.taskDescription || '';
                } else {
                    if (btnDropdownEditTask) btnDropdownEditTask.classList.add('d-none');
                    if (btnDropdownDeleteTask) btnDropdownDeleteTask.classList.add('d-none');
                }

                // Render Deskripsi
                taskDescription.innerHTML = linkifyDescription(task.taskDescription || 'Tidak ada deskripsi.');

                // Render Grid Lampiran File
                renderTaskFilesGrid(files);
                renderTaskEditableFiles(files);

                // Render Tombol "Tandai Selesai" & "Bagikan Jawaban"
                updateToggleCompleteButtonUI();
                updateShareAnswerButtonUI();

                // Render Daftar Mahasiswa Selesai & Komentar
                renderCompletions(completions);
                renderComments(comments);
            })
            .catch(err => {
                skeletonLoader.classList.add('d-none');
                console.error('Error fetching task details:', err);
                alertContainer.innerHTML = '<div class="alert alert-danger rounded-4">Terjadi kesalahan koneksi saat memuat detail tugas.</div>';
            });
    }

    // ====================================================================================
    // 3. GRID LAMPIRAN FILE
    // ====================================================================================

    function renderTaskFilesGrid(files) {
        const taskFilesDivider = document.getElementById('taskFilesDivider');
        if (!files || files.length === 0) {
            taskFilesGrid.innerHTML = '';
            if (noFilesText) noFilesText.classList.add('d-none');
            if (taskFilesDivider) taskFilesDivider.classList.add('d-none');
            return;
        }

        if (noFilesText) noFilesText.classList.add('d-none');
        if (taskFilesDivider) taskFilesDivider.classList.remove('d-none');

        taskFilesGrid.innerHTML = files.map(file => {
            const fileUrl = /^https?:\/\//i.test(file.filePath) ? file.filePath : `${BASE_URL}${encodeURI(file.filePath)}`;
            let downloadUrl = fileUrl;
            const driveMatch = file.filePath ? file.filePath.match(/\/d\/([a-zA-Z0-9_-]+)/) : null;
            if (driveMatch && driveMatch[1]) {
                downloadUrl = `https://drive.google.com/uc?export=download&id=${driveMatch[1]}`;
            }

            const meta = getFileIconMeta(file.fileName);

            return `
                <div class="col">
                    <div class="card border-0 shadow-none bg-transparent rounded-3 p-2 text-center h-100 position-relative file-grid-card">
                        <a href="${downloadUrl}" download="${escapeHtml(file.fileName)}" class="btn btn-sm btn-light border-0 rounded-circle position-absolute top-0 end-0 m-1 p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" title="Unduh File" onclick="event.stopPropagation();">
                            <i class="bi bi-download text-secondary" style="font-size: 1.0rem;"></i>
                        </a>

                        <a href="${fileUrl}" target="_blank" rel="noopener noreferrer" class="text-decoration-none d-block py-1">
                            <div class="my-1">
                                <i class="bi ${meta.icon}" style="font-size: 2.0rem; color: ${meta.color};"></i>
                            </div>
                            <div class="fw-semibold text-dark text-truncate small mt-1 px-1" title="${escapeHtml(file.fileName)}" style="font-size: 0.8rem;">
                                ${escapeHtml(file.fileName)}
                            </div>
                            <small class="text-muted d-block mt-0.5" style="font-size: 0.7rem;">
                                ${formatFileSize(file.fileSize)}
                            </small>
                        </a>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderTaskEditableFiles(files) {
        if (!editTaskExistingFiles) return;
        editTaskExistingFiles.innerHTML = files.length ? files.map(file => `
            <div class="list-group-item d-flex align-items-center justify-content-between gap-2 border-0 bg-light rounded-3 mb-1">
                <span class="text-truncate small">${escapeHtml(file.fileName)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-delete-task-file="${Number(file.fileId)}">Hapus</button>
            </div>`).join('') : '<div class="text-muted small">Tidak ada lampiran saat ini.</div>';
    }

    editTaskExistingFiles?.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-task-file]');
        if (!button || !await window.appConfirm('Hapus lampiran ini secara permanen?')) return;
        button.disabled = true;
        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_attachment', fileId: button.dataset.deleteTaskFile })
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Lampiran gagal dihapus.');
            window.appToast?.('Lampiran dihapus.', 'success');
            loadTaskDetail();
        }).catch(error => {
            window.appToast?.(error.message, 'danger');
            button.disabled = false;
        });
    });

    // ====================================================================================
    // 4. TOMBOL "TANDAI SELESAI" (EFEK INVERTED HOVER)
    // ====================================================================================

    function updateToggleCompleteButtonUI() {
        if (!btnToggleComplete) return;

        if (isTaskCompletedByUser) {            
            completeBtnText.textContent = 'Batal Selesai';
        } else {            
            completeBtnText.textContent = 'Tandai Selesai';
        }
    }

    btnToggleComplete.addEventListener('click', function () {
        btnToggleComplete.disabled = true;

        const formData = new FormData();
        formData.append('action', 'toggle_completion');
        formData.append('taskId', CURRENT_TASK_ID);

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                isTaskCompletedByUser = Boolean(res.isCompleted);
                updateToggleCompleteButtonUI();
                updateShareAnswerButtonUI();
                loadTaskDetail();
            } else {
                window.appToast?.('Gagal mengubah status: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Toggle Error:', err);
            window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
        })
        .finally(() => {
            btnToggleComplete.disabled = false;
        });
    });

    // ====================================================================================
    // 5. MAHASISWA YANG TELAH SELESAI
    // ====================================================================================

    function renderCompletions(completions) {
        if (!completionCountBadge || !completionsList) return;

        const count = completions ? completions.length : 0;
        if (count > 0) {
            completionCountBadge.textContent = count;
            completionCountBadge.classList.remove('d-none');
        } else {
            completionCountBadge.classList.add('d-none');
        }

        if (!completions || completions.length === 0) {
            completionsList.innerHTML = '<li class="list-group-item text-muted text-center py-4 fst-italic border-0">Belum ada mahasiswa yang menyelesaikan tugas ini.</li>';
            return;
        }

        completionsList.innerHTML = completions.map((c, idx) => {
            const isAnonymous = parseInt(c.hideCompletedIdentity) === 1;
            const rawName = isAnonymous ? 'Mahasiswa Rahasia' : (c.userName || 'Pengguna Nonaktif');
            const truncatedName = truncateUsername(rawName, 20);
            const avatarUrl = (isAnonymous || !c.avatarUrl) ? `${BASE_URL}public/assets/img/logo.png` : resolveAssetUrl(c.avatarUrl);
            const roleBadge = isAnonymous ? '' : roleBadgeCompactHtml(c.roleLevel);
            const answerBtn = c.answerId ? `
                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" data-view-answer-id="${c.answerId}">
                    Lihat Jawaban
                </button>
            ` : '';
            const borderClass = idx < completions.length - 1 ? 'border-bottom border-light-subtle' : '';

            return `
                <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2.5 border-0 ${borderClass}">
                    <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                        <img src="${avatarUrl}" class="rounded-circle border flex-shrink-0" style="width: 34px; height: 34px; object-fit: cover;" alt="Avatar" onerror="this.src='${BASE_URL}public/assets/img/logo.png'">
                        <div class="overflow-hidden">
                            <div class="fw-semibold text-dark text-truncate small" title="${escapeHtml(rawName)}">
                                ${escapeHtml(truncatedName)}
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                ${roleBadge}
                                <span class="text-muted" style="font-size: 0.72rem;"> ${formatIndoDate(c.completedAt)}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        ${answerBtn}
                    </div>
                </li>
            `;
        }).join('');
    }

    // ====================================================================================
    // 6. TOMBOL "BAGIKAN JAWABAN" & ALUR MODAL
    // ====================================================================================

    function updateShareAnswerButtonUI() {
        if (!btnShareAnswer) return;

        if (!isTaskCompletedByUser) {
            btnShareAnswer.disabled = true;
            btnShareAnswer.title = 'Tandai tugas ini selesai terlebih dahulu untuk membagikan jawaban.';
            btnShareAnswer.className = 'btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold w-100';
            btnShareAnswer.textContent = 'Bagikan Jawaban';
            return;
        }

        btnShareAnswer.disabled = false;
        btnShareAnswer.removeAttribute('title');

        if (mySharedAnswerId) {       
            btnShareAnswer.textContent = 'Batal bagikan jawaban';
        } else {         
            btnShareAnswer.textContent = 'Bagikan Jawaban';
        }
    }

    btnShareAnswer?.addEventListener('click', async function () {
        if (!isTaskCompletedByUser) {
            window.appToast?.('Anda harus menandai tugas selesai terlebih dahulu.', 'warning');
            return;
        }

        if (mySharedAnswerId) {
            // Aksi membatalkan pembagian jawaban
            if (!await window.appConfirm('Batalkan pembagian jawaban dan hapus jawaban Anda secara permanen?')) return;
            btnShareAnswer.disabled = true;

            fetch(`${BASE_URL}app/api/task_answers.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ answerId: mySharedAnswerId })
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    window.appToast?.('Jawaban berhasil dibatalkan.', 'success');
                    mySharedAnswerId = null;
                    loadTaskDetail();
                } else {
                    window.appToast?.(result.message || 'Gagal membatalkan jawaban.', 'danger');
                    btnShareAnswer.disabled = false;
                }
            })
            .catch(err => {
                console.error('Cancel share answer error:', err);
                window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
                btnShareAnswer.disabled = false;
            });
        } else {
            // Membuka modal bagikan jawaban baru
            formShareAnswer.reset();
            const shareModal = new bootstrap.Modal(document.getElementById('shareAnswerModal'));
            shareModal.show();
        }
    });

    // Submit Form Bagikan Jawaban Baru
    formShareAnswer?.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = btnSubmitShareAnswer;
        const formData = new FormData(formShareAnswer);

        window.uploadWithProgress(`${BASE_URL}app/api/task_answers.php`, formData, submitBtn)
            .then(result => {
                if (result.success) {
                    window.appToast?.('Jawaban berhasil dibagikan.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('shareAnswerModal'))?.hide();
                    formShareAnswer.reset();
                    loadTaskDetail();
                } else {
                    window.appToast?.(result.message || 'Gagal membagikan jawaban.', 'danger');
                }
            })
            .catch(err => {
                console.error('Share answer submit error:', err);
                window.appToast?.('Terjadi kesalahan server.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Bagikan Jawaban';
            });
    });

    // ====================================================================================
    // 7. MODAL "LIHAT JAWABAN" & INLINE EDIT MODE (DENGAN DIRTY CHECKING)
    // ====================================================================================

    document.addEventListener('click', function (e) {
        const viewBtn = e.target.closest('[data-view-answer-id]');
        if (viewBtn) {
            const answerId = Number(viewBtn.dataset.viewAnswerId);
            openAnswerDetailModal(answerId);
        }
    });

    function openAnswerDetailModal(answerId) {
        currentlyViewingAnswerId = answerId;
        const modal = new bootstrap.Modal(viewAnswerModalEl);
        modal.show();

        fetch(`${BASE_URL}app/api/task_answers.php?action=detail&answerId=${answerId}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.data) {
                    window.appToast?.(res.message || 'Gagal memuat jawaban.', 'danger');
                    modal.hide();
                    return;
                }

                const answer = res.data.answer;
                const files = res.data.files || [];
                const canEdit = Boolean(res.data.canEdit);

                // Header Pengunggah Jawaban
                modalAnswerAuthorAvatar.src = resolveAssetUrl(answer.avatarUrl);
                modalAnswerAuthorName.textContent = truncateUsername(answer.userName, 20);
                modalAnswerDateMeta.innerHTML = `${roleBadgeCompactHtml(answer.roleLevel)} <span class="ms-1">${formatIndoDate(answer.createdAt)}</span>`;

                // Opsi Dropdown Titik Tiga
                if (canEdit) {
                    btnDeleteOwnAnswer.classList.remove('d-none');
                } else {
                    btnDeleteOwnAnswer.classList.add('d-none');
                }

                if (canEdit) {
                    // Mode Edit Otomatis untuk Jawaban Milik Sendiri
                    readOnlyAnswerContent.classList.add('d-none');
                    readOnlyAnswerFooter.classList.add('d-none');
                    formEditAnswer.classList.remove('d-none');

                    editAnswerIdHidden.value = answer.answerId;
                    editAnswerTitleInput.value = answer.answerTitle || '';
                    editAnswerNotesInput.value = answer.answerNotes || '';
                    editAnswerNewAttachments.value = '';

                    initialAnswerTitle = (answer.answerTitle || '').trim();
                    initialAnswerNotes = (answer.answerNotes || '').trim();

                    // Render File Lampiran yang Sudah Ada
                    renderAnswerExistingFiles(files);

                    // Reset Status Tombol Simpan
                    btnSaveAnswerEdit.disabled = true;
                } else {
                    // Mode Read-Only untuk Jawaban Milik Mahasiswa Lain
                    formEditAnswer.classList.add('d-none');
                    readOnlyAnswerContent.classList.remove('d-none');
                    readOnlyAnswerFooter.classList.remove('d-none');

                    readOnlyAnswerTitle.textContent = answer.answerTitle;
                    readOnlyAnswerNotes.innerHTML = linkifyDescription(answer.answerNotes || 'Tidak ada catatan tambahan.');

                    // Render File Grid Read-Only
                    renderAnswerReadOnlyFilesGrid(files);
                }
            })
            .catch(err => {
                console.error('Error fetching answer detail:', err);
                window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
                modal.hide();
            });
    }

    // Render Grid File Jawaban Read-Only
    function renderAnswerReadOnlyFilesGrid(files) {
        if (!files || files.length === 0) {
            readOnlyAnswerFilesGrid.innerHTML = '';
            noAnswerFilesText.classList.remove('d-none');
            return;
        }

        noAnswerFilesText.classList.add('d-none');

        readOnlyAnswerFilesGrid.innerHTML = files.map(file => {
            const fileUrl = /^https?:\/\//i.test(file.filePath) ? file.filePath : `${BASE_URL}${encodeURI(file.filePath)}`;
            let downloadUrl = fileUrl;
            const driveMatch = file.filePath ? file.filePath.match(/\/d\/([a-zA-Z0-9_-]+)/) : null;
            if (driveMatch && driveMatch[1]) {
                downloadUrl = `https://drive.google.com/uc?export=download&id=${driveMatch[1]}`;
            }

            const meta = getFileIconMeta(file.fileName);

            return `
                <div class="col">
                    <div class="card border-0 shadow-none bg-transparent rounded-3 p-2 text-center h-100 position-relative file-grid-card">
                        <a href="${downloadUrl}" download="${escapeHtml(file.fileName)}" class="btn btn-sm btn-light border-0 rounded-circle position-absolute top-0 end-0 m-1 p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" title="Unduh File" onclick="event.stopPropagation();">
                            <i class="bi bi-download text-secondary" style="font-size: 0.75rem;"></i>
                        </a>

                        <a href="${fileUrl}" target="_blank" rel="noopener noreferrer" class="text-decoration-none d-block py-1">
                            <div class="my-1">
                                <i class="bi ${meta.icon}" style="font-size: 1.75rem; color: ${meta.color};"></i>
                            </div>
                            <div class="fw-semibold text-dark text-truncate small mt-1 px-1" title="${escapeHtml(file.fileName)}" style="font-size: 0.8rem;">
                                ${escapeHtml(file.fileName)}
                            </div>
                            <small class="text-muted d-block mt-0.5" style="font-size: 0.7rem;">
                                ${formatFileSize(file.fileSize)}
                            </small>
                        </a>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Render File yang Sudah Ada di Form Edit Jawaban
    function renderAnswerExistingFiles(files) {
        if (!editAnswerExistingFiles) return;
        editAnswerExistingFiles.innerHTML = files.length ? files.map(file => `
            <div class="list-group-item d-flex align-items-center justify-content-between gap-2 border-0 bg-light rounded-3 mb-1">
                <span class="text-truncate small">${escapeHtml(file.fileName)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-delete-answer-file="${Number(file.fileId)}">Hapus</button>
            </div>`).join('') : '<div class="text-muted small">Tidak ada lampiran saat ini.</div>';
    }

    // Hapus Lampiran File Jawaban
    editAnswerExistingFiles?.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-answer-file]');
        if (!button || !await window.appConfirm('Hapus lampiran ini secara permanen?')) return;
        button.disabled = true;

        fetch(`${BASE_URL}app/api/task_answers.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_attachment', fileId: button.dataset.deleteAnswerFile })
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) throw new Error(result.message || 'Lampiran gagal dihapus.');
            window.appToast?.('Lampiran dihapus.', 'success');
            if (currentlyViewingAnswerId) {
                openAnswerDetailModal(currentlyViewingAnswerId);
            }
        })
        .catch(error => {
            window.appToast?.(error.message, 'danger');
            button.disabled = false;
        });
    });

    // Dirty Checking untuk Form Edit Jawaban
    function checkAnswerDirty() {
        if (!btnSaveAnswerEdit) return;

        const currentTitle = (editAnswerTitleInput.value || '').trim();
        const currentNotes = (editAnswerNotesInput.value || '').trim();
        const hasNewFiles = editAnswerNewAttachments.files && editAnswerNewAttachments.files.length > 0;

        const isTitleChanged = currentTitle !== initialAnswerTitle;
        const isNotesChanged = currentNotes !== initialAnswerNotes;
        const isDirty = (isTitleChanged || isNotesChanged || hasNewFiles) && currentTitle.length > 0;

        btnSaveAnswerEdit.disabled = !isDirty;
    }

    editAnswerTitleInput?.addEventListener('input', checkAnswerDirty);
    editAnswerNotesInput?.addEventListener('input', checkAnswerDirty);
    editAnswerNewAttachments?.addEventListener('change', checkAnswerDirty);

    // Submit Simpan Perubahan Jawaban
    formEditAnswer?.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = btnSaveAnswerEdit;
        const formData = new FormData(formEditAnswer);

        window.uploadWithProgress(`${BASE_URL}app/api/task_answers.php`, formData, submitBtn)
            .then(result => {
                if (result.success) {
                    window.appToast?.('Jawaban berhasil diperbarui.', 'success');
                    bootstrap.Modal.getInstance(viewAnswerModalEl)?.hide();
                    loadTaskDetail();
                } else {
                    window.appToast?.(result.message || 'Gagal memperbarui jawaban.', 'danger');
                }
            })
            .catch(err => {
                console.error('Update answer error:', err);
                window.appToast?.('Terjadi kesalahan server saat memperbarui jawaban.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Simpan Perubahan';
            });
    });

    // Salin Link Jawaban
    btnCopyAnswerLink?.addEventListener('click', function () {
        const url = window.location.href;
        navigator.clipboard.writeText(url)
            .then(() => window.appToast?.('Tautan tugas disalin ke papan klip.', 'success'))
            .catch(() => window.appToast?.('Gagal menyalin tautan.', 'danger'));
    });

    // Hapus Jawaban dari Menu Titik Tiga Modal
    btnDeleteOwnAnswer?.addEventListener('click', async function () {
        if (!currentlyViewingAnswerId) return;
        if (!await window.appConfirm('Hapus jawaban ini secara permanen beserta seluruh file lampirannya?')) return;

        btnDeleteOwnAnswer.disabled = true;

        fetch(`${BASE_URL}app/api/task_answers.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ answerId: currentlyViewingAnswerId })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                window.appToast?.('Jawaban berhasil dihapus.', 'success');
                bootstrap.Modal.getInstance(viewAnswerModalEl)?.hide();
                loadTaskDetail();
            } else {
                window.appToast?.(result.message || 'Gagal menghapus jawaban.', 'danger');
                btnDeleteOwnAnswer.disabled = false;
            }
        })
        .catch(err => {
            console.error('Delete answer error:', err);
            window.appToast?.('Terjadi kesalahan saat menghapus jawaban.', 'danger');
            btnDeleteOwnAnswer.disabled = false;
        });
    });

    // ====================================================================================
    // 8. KOMENTAR (DESAIN FORM & LOGIKA EDIT GAYA GLOBAL CHAT)
    // ====================================================================================

    function renderComments(comments) {
        if (!commentsContainer) return;

        if (!comments || comments.length === 0) {
            commentsContainer.innerHTML = '<div class="text-muted text-center py-4 fst-italic">Belum ada diskusi. Jadilah yang pertama berkomentar!</div>';
            return;
        }

        commentsContainer.innerHTML = comments.map(c => {
            const avatar = resolveAssetUrl(c.avatarUrl);
            const truncatedName = truncateUsername(c.userName, 20);

            const ownerMenu = c.isOwner ? `
                <div class="dropdown">
                    <button class="btn btn-sm btn-link text-secondary p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Komentar">
                        <i class="bi bi-three-dots-vertical fs-6"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 py-1">
                        <li>
                            <button class="dropdown-item py-1.5 small d-flex align-items-center gap-2" type="button" data-edit-comment-id="${c.commentId}" data-comment-content="${escapeHtml(c.commentContent)}">
                                <i class="bi bi-pencil"></i>Edit
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-1.5 small text-danger d-flex align-items-center gap-2" type="button" data-delete-comment-id="${c.commentId}">
                                <i class="bi bi-trash"></i>Hapus
                            </button>
                        </li>
                    </ul>
                </div>
            ` : '';

            return `
                <div class="d-flex align-items-start gap-3 comment-row" data-comment-id="${c.commentId}">
                    <!-- Kolom Avatar & Badge Level Berjajar Vertikal Rata Tengah -->
                    <div class="comment-avatar-badge-col">
                        <img src="${avatar}" class="rounded-circle border" style="width: 36px; height: 36px; object-fit: cover; display: block; margin: 0 auto 3px auto;" alt="Avatar" onerror="this.src='${BASE_URL}public/assets/img/logo.png'">
                        ${roleBadgeCompactHtml(c.roleLevel)}
                    </div>

                    <!-- Bubble Isi Komentar (Tanpa Background Gelap) -->
                    <div class="comment-bubble flex-grow-1 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-bold text-dark small" title="${escapeHtml(c.userName)}">${escapeHtml(truncatedName)}</span>
                                <small class="text-muted" style="font-size: 0.72rem;">${formatIndoDate(c.createdAt)}</small>
                            </div>
                            ${ownerMenu}
                        </div>
                        <div class="comment-text text-break text-secondary" style="font-size: 0.88rem; white-space: pre-wrap; line-height: 1.5;">${escapeHtml(c.commentContent)}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Dynamic Textarea Auto-Expand Komentar
    if (commentContent) {
        commentContent.addEventListener('input', function () {
            this.style.height = 'auto';
            const newHeight = Math.min(this.scrollHeight, 80);
            this.style.height = newHeight + 'px';
            this.style.overflowY = this.scrollHeight > 80 ? 'auto' : 'hidden';
        });

        commentContent.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (commentForm) {
                    commentForm.requestSubmit();
                }
            }
        });
    }

    // Set & Cancel Mode Edit Komentar
    function setEditCommentMode(commentId, currentText) {
        cancelEditCommentMode();

        editingCommentId = Number(commentId);
        if (commentContent) {
            commentContent.value = currentText || '';
            commentContent.style.height = 'auto';
            commentContent.style.height = Math.min(commentContent.scrollHeight, 80) + 'px';
            commentContent.focus();
        }

        const row = commentsContainer ? commentsContainer.querySelector(`.comment-row[data-comment-id="${commentId}"]`) : null;
        if (row) {
            const b = row.querySelector('.comment-bubble');
            if (b) b.classList.add('comment-bubble-editing');
        }

        if (btnCancelEditComment) {
            btnCancelEditComment.classList.remove('d-none');
            btnCancelEditComment.classList.add('d-flex');
        }
        if (btnSubmitCommentIcon) {
            btnSubmitCommentIcon.className = 'bi bi-pencil-fill fs-6';
        }
        if (btnSubmitComment) {
            btnSubmitComment.title = 'Simpan Perubahan';
        }
    }

    function cancelEditCommentMode() {
        editingCommentId = null;
        if (commentContent) {
            commentContent.value = '';
            commentContent.style.height = 'auto';
        }
        if (commentsContainer) {
            commentsContainer.querySelectorAll('.comment-bubble-editing').forEach(el => el.classList.remove('comment-bubble-editing'));
        }
        if (btnCancelEditComment) {
            btnCancelEditComment.classList.add('d-none');
            btnCancelEditComment.classList.remove('d-flex');
        }
        if (btnSubmitCommentIcon) {
            btnSubmitCommentIcon.className = 'bi bi-send-fill fs-6';
        }
        if (btnSubmitComment) {
            btnSubmitComment.title = 'Kirim Komentar';
        }
    }

    btnCancelEditComment?.addEventListener('click', cancelEditCommentMode);

    // Event Delegation Edit & Hapus Komentar
    commentsContainer?.addEventListener('click', async function (e) {
        const editBtn = e.target.closest('[data-edit-comment-id]');
        if (editBtn) {
            const commentId = Number(editBtn.dataset.editCommentId);
            const content = editBtn.dataset.commentContent || '';
            setEditCommentMode(commentId, content);
            return;
        }

        const deleteBtn = e.target.closest('[data-delete-comment-id]');
        if (deleteBtn) {
            const commentId = Number(deleteBtn.dataset.deleteCommentId);
            if (!await window.appConfirm('Hapus komentar ini?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('commentId', commentId);

            fetch(`${BASE_URL}app/api/tasks.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.appToast?.('Komentar dihapus.', 'success');
                    if (editingCommentId === commentId) {
                        cancelEditCommentMode();
                    }
                    loadTaskDetail();
                } else {
                    window.appToast?.(res.message || 'Gagal menghapus komentar.', 'danger');
                }
            })
            .catch(err => {
                console.error('Delete comment error:', err);
                window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
            });
        }
    });

    // Form Submit Komentar (Tambah / Update)
    commentForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        const content = commentContent.value.trim();
        if (content === '') return;

        btnSubmitComment.disabled = true;

        const formData = new FormData();
        if (editingCommentId !== null) {
            formData.append('action', 'edit_comment');
            formData.append('commentId', editingCommentId);
            formData.append('commentContent', content);
        } else {
            formData.append('action', 'add_comment');
            formData.append('taskId', CURRENT_TASK_ID);
            formData.append('commentContent', content);
        }

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                cancelEditCommentMode();
                loadTaskDetail();
            } else {
                window.appToast?.('Gagal memproses komentar: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Comment Error:', err);
            window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
        })
        .finally(() => {
            btnSubmitComment.disabled = false;
        });
    });

    // ====================================================================================
    // 9. FORM HANDLER EDIT TUGAS & HAPUS TUGAS
    // ====================================================================================

    formEditTask?.addEventListener('submit', function (e) {
        e.preventDefault();
        const btnSubmit = btnSubmitEditTask;
        const formData = new FormData(this);

        window.uploadWithProgress(`${BASE_URL}app/api/tasks.php`, formData, btnSubmit)
            .then(res => {
                if (res.success) {
                    window.appToast?.('Tugas berhasil diperbarui.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editTaskModal'))?.hide();
                    window.clearMultiFileInputs?.(formEditTask);
                    if (editTaskAttachments) editTaskAttachments.value = '';
                    loadTaskDetail();
                } else {
                    window.appToast?.('Gagal: ' + res.message, 'danger');
                }
            })
            .catch(err => {
                console.error('Edit Error:', err);
                window.appToast?.('Terjadi kesalahan server saat memperbarui tugas.', 'danger');
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Simpan Perubahan';
            });
    });

    btnDropdownDeleteTask?.addEventListener('click', async function () {
        if (!await window.appConfirm('Tugas akan dikeluarkan dari daftar aktif dan lampirannya dihapus.')) return;
        btnDropdownDeleteTask.disabled = true;

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ taskId: CURRENT_TASK_ID })
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                window.appToast?.('Tugas berhasil dihapus.', 'success');
                window.location.href = backToCourseBtn.href;
            } else {
                window.appToast?.('Gagal menghapus: ' + res.message, 'danger');
                btnDropdownDeleteTask.disabled = false;
            }
        })
        .catch(err => {
            console.error('Delete Error:', err);
            window.appToast?.('Terjadi kesalahan saat menghapus tugas.', 'danger');
            btnDropdownDeleteTask.disabled = false;
        });
    });

    // Eksekusi Awal
    loadTaskDetail();
});