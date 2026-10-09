document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('globalTasksList');
    const alertBox = document.getElementById('globalTasksAlert');
    const filterButtons = document.querySelectorAll('.task-filter-btn');

    let currentFilter = typeof GLOBAL_TASK_FILTER !== 'undefined' ? GLOBAL_TASK_FILTER : 'all';

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

    function updateFilterUI() {
        filterButtons.forEach(btn => {
            const filterType = btn.getAttribute('data-filter');
            if (filterType === currentFilter) {
                btn.className = 'btn btn-sm rounded-pill px-3 fw-bold btn-dark shadow-sm task-filter-btn';
            } else {
                btn.className = 'btn btn-sm rounded-pill px-3 fw-semibold btn-outline-secondary task-filter-btn';
            }
        });
    }

    async function loadTasks(filter) {
        currentFilter = filter;
        updateFilterUI();

        if (alertBox) alertBox.innerHTML = '';
        if (list) {
            list.innerHTML = `
                <div class="text-center text-muted py-5">
                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                    Memuat tugas...
                </div>`;
        }

        try {
            const response = await fetch(`${BASE_URL}app/api/tasks.php?action=global_list&filter=${encodeURIComponent(currentFilter)}`);
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (error) {
                throw new Error('Server mengembalikan respons yang bukan JSON.');
            }

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal memuat tugas.');
            }

            if (result.data.semester) {
                const sem = result.data.semester;
                const semTitleEl = document.getElementById('tasksActiveSemesterTitle');
                const semInfoEl = document.getElementById('tasksActiveSemesterInfo');
                if (semTitleEl) semTitleEl.textContent = sem.semesterTitle || 'Semester Aktif';
                if (semInfoEl) semInfoEl.textContent = `${sem.studyProgram || '-'} (${sem.majorType || '-'}) - Kelas ${sem.classGroup || '-'} | Angkatan ${sem.batchYear || '-'}`;
            } else {
                const semTitleEl = document.getElementById('tasksActiveSemesterTitle');
                const semInfoEl = document.getElementById('tasksActiveSemesterInfo');
                if (semTitleEl) semTitleEl.textContent = 'Tidak Ada Semester Aktif';
                if (semInfoEl) semInfoEl.textContent = 'Daftar tugas kosong sampai semester diaktifkan.';
            }

            const tasks = result.data.tasks || [];
            renderTasks(tasks, !result.data.semester);

        } catch (error) {
            if (alertBox) alertBox.innerHTML = `<div class="alert alert-danger shadow-sm rounded-3">${escapeHtml(error.message)}</div>`;
            if (list) list.innerHTML = '';
        }
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const day = d.getDate();
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day} ${month} ${year}, ${hours}:${minutes}`;
    }

    function formatRemainingTime(diffMs) {
        if (diffMs <= 0) return 'Deadline Tiba';
        const totalMinutes = Math.floor(diffMs / (1000 * 60));
        const totalHours = Math.floor(totalMinutes / 60);
        const days = Math.floor(totalHours / 24);
        const hours = totalHours % 24;
        const minutes = totalMinutes % 60;

        let result = '';
        if (days > 0) {
            result += `${days} hari `;
            if (hours > 0) {
                result += `${hours} jam `;
            }
        } else if (hours > 0) {
            result += `${hours} jam `;
            if (minutes > 0) {
                result += `${minutes} menit `;
            }
        } else {
            result += `${minutes > 0 ? minutes : 1} menit `;
        }
        return `${result.trim()} lagi`;
    }

    function renderTasks(tasks, noActiveSemester = false) {
        if (!list) return;

        if (!tasks.length) {
            list.innerHTML = `
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                        <h6 class="fw-semibold text-dark mb-1">Tidak Ada Tugas</h6>
                        <p class="text-muted small mb-0">${noActiveSemester ? 'Belum ada semester aktif.' : 'Tidak ada tugas pada filter ini.'}</p>
                    </div>
                </div>`;
            return;
        }

        const now = new Date();

        list.innerHTML = tasks.map(task => {
            const isCompleted = Number(task.isCompleted) > 0;
            const dueDate = task.dueDate ? new Date(task.dueDate.replace(/-/g, '/')) : null;
            const isMissed = !isCompleted && dueDate && (dueDate < now);

            let statusBadgeHtml = '';
            let countdownBadgeHtml = '';

            if (isCompleted) {
                statusBadgeHtml = `<span class="badge bg-success text-white rounded-pill px-3 py-1.5 fw-medium"><i class="bi bi-check2-circle me-1"></i>Selesai</span>`;
            } else if (isMissed) {
                statusBadgeHtml = `<span class="badge bg-danger text-white rounded-pill px-3 py-1.5 fw-bold shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Terlewat</span>`;
            } else {
                statusBadgeHtml = `<span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 fw-medium"><i class="bi bi-clock-history me-1"></i>Pending</span>`;

                if (dueDate) {
                    const diffMs = dueDate.getTime() - now.getTime();
                    const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
                    const remainingText = formatRemainingTime(diffMs);

                    if (totalHours <= 24) {
                        // Kategori 1: <= 24 Jam (H-1): Teks Merah
                        countdownBadgeHtml = `<small class="text-danger fw-bold ms-1">(${escapeHtml(remainingText)})</small>`;
                    } else if (totalHours <= 168) {
                        // Kategori 2: > 24 Jam s.d. 7 Hari: Teks Amber/Kuning Kontras
                        countdownBadgeHtml = `<small class="fw-semibold ms-1" style="color: #d97706;">(${escapeHtml(remainingText)})</small>`;
                    } else {
                        // Kategori 3: > 7 Hari: Teks Hijau
                        countdownBadgeHtml = `<small class="text-success fw-medium ms-1">(${escapeHtml(remainingText)})</small>`;
                    }
                }
            }

            const borderLeftStyle = isMissed
                ? 'border-left: 5px solid #dc3545 !important;'
                : (isCompleted ? 'border-left: 5px solid #198754 !important;' : 'border-left: 5px solid #ffc107 !important;');

            // Tipe Mata Kuliah (Teori / Praktek) TANPA IKON - Desain 100% Identik dengan Warna Jadwal Kuliah
            const courseTypeStr = (task.courseType || 'Teori').trim();
            const isPraktek = courseTypeStr.toLowerCase() === 'praktek';
            const typeBadgeClass = isPraktek ? 'bg-warning text-dark' : 'bg-primary text-white';
            const courseTypeBadgeHtml = `<span class="badge ${typeBadgeClass} rounded-pill px-2.5 py-1 small fw-medium align-middle">${escapeHtml(courseTypeStr)}</span>`;

            // Badge tipe spesifik jika bukan 'Tugas' (misal: Kuis, UTS, UAS, Praktikum)
            let taskTypeBadgeHtml = '';
            if (task.taskType && task.taskType.trim().toLowerCase() !== 'tugas') {
                taskTypeBadgeHtml = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 small fw-semibold align-middle">${escapeHtml(task.taskType)}</span>`;
            }

            // Link Anchor ke Detail Course
            const courseDetailUrl = `${BASE_URL}course-detail?id=${task.courseId}&from=global&filter=${encodeURIComponent(currentFilter)}`;
            const courseLinkHtml = `<a href="${courseDetailUrl}" onclick="event.stopPropagation();" class="text-decoration-none text-dark fw-bold me-2 fs-6 hover-primary align-middle">${escapeHtml(task.courseTitle)}</a>`;

            const detailUrl = `${BASE_URL}task-detail?id=${task.taskId}&from=global&filter=${encodeURIComponent(currentFilter)}`;

            return `
                <div onclick="window.location.href='${detailUrl}'"
                     class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden task-card-item mb-1"
                     style="${borderLeftStyle}">
                    <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                <i class="bi bi-mortarboard-fill text-primary me-2 align-middle fs-6"></i>
                                ${courseLinkHtml}
                                ${courseTypeBadgeHtml}
                                ${taskTypeBadgeHtml}
                            </div>
                            <h6 class="fw-bold text-dark mb-2 fs-6 ps-0">${escapeHtml(task.taskTitle)}</h6>
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                <small class="${isMissed ? 'text-danger fw-bold' : 'text-muted'} align-middle">
                                    <i class="bi bi-calendar-event me-1"></i>${escapeHtml(formatDateTime(task.dueDate))}
                                </small>
                                ${countdownBadgeHtml}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${statusBadgeHtml}
                            <div class="ms-1 text-secondary">
                                <i class="bi bi-chevron-right fs-5"></i>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.getAttribute('data-filter');
            if (filter !== currentFilter) {
                try {
                    window.history.pushState(null, '', `${BASE_URL}tasks?filter=${filter}`);
                } catch(e) {}
                loadTasks(filter);
            }
        });
    });

    loadTasks(currentFilter);
});
