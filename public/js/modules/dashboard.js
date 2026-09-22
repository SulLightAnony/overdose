document.addEventListener("DOMContentLoaded", () => {
    fetchDashboardData();
});

function renderRoleElement(role) {
    if (!role) return `<span class="text-muted d-block" style="font-size: 0.72rem;">Keroco</span>`;
    const lower = role.toLowerCase();
    if (lower === 'primordial') {
        return `<span class="badge mt-1" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.65rem; font-weight: 600;">Primordial</span>`;
    } else if (lower === 'sepuh') {
        return `<span class="badge bg-secondary text-white mt-1" style="font-size: 0.65rem;">Sepuh</span>`;
    }
    return `<span class="text-muted d-block" style="font-size: 0.72rem;">Keroco</span>`;
}

async function fetchDashboardData() {
    const quoteContainer = document.getElementById('quote-container');
    const statDone = document.getElementById('stat-done');
    const statPending = document.getElementById('stat-pending');
    const statMissed = document.getElementById('stat-missed');
    const taskContainer = document.getElementById('pending-tasks-list');
    const topContainer = document.getElementById('top-contributors-list');

    try {
        const fetchUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/dashboard.php';
        const response = await fetch(fetchUrl);
        const res = await response.json();

        if (res.success && res.data) {
            const data = res.data;

            // 1. Render Quote
            if (quoteContainer) {
                const quoteText = data.quote?.quote || 'Tetap semangat menjalani perkuliahan!';
                const quoteAuthor = data.quote?.author || 'Overdose Team';
                quoteContainer.innerHTML = `"${quoteText}" &mdash; <strong>${quoteAuthor}</strong>`;
            }

            // 2. Render Angka Statistik
            if (statDone) statDone.textContent = data.stats.done ?? 0;
            if (statPending) statPending.textContent = data.stats.pending ?? 0;
            if (statMissed) statMissed.textContent = data.stats.missed ?? 0;

            // 3. Render Daftar Tugas
            if (taskContainer) {
                taskContainer.innerHTML = '';
                if (!data.pendingTasks || data.pendingTasks.length === 0) {
                    taskContainer.innerHTML = `
                        <div class="text-center text-muted small py-4">
                            <i class="bi bi-emoji-smile fs-3 d-block mb-1 text-success"></i>
                            Tidak ada tugas! Yey!
                        </div>`;
                } else {
                    data.pendingTasks.forEach(task => {
                        const baseUrlPath = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
                        taskContainer.innerHTML += `
                            <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 bg-white hover-shadow transition-all">
                                <div class="d-flex align-items-center gap-3">
                                    <input class="form-check-input fs-5 mt-0 task-checkbox" type="checkbox" data-id="${task.taskId}">
                                    <div>
                                        <h6 class="mb-0 fw-semibold text-dark fs-6">${task.title}</h6>
                                        <small class="text-danger fw-medium" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock-history me-1"></i>${task.deadline}
                                        </small>
                                    </div>
                                </div>
                                <a href="${baseUrlPath}task/detail?id=${task.taskId}" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold">Detail</a>
                            </div>
                        `;
                    });
                }
            }

            // 4. Render Top Contributors & Personal Section
            if (topContainer) {
                topContainer.innerHTML = '';
                const currentUserId = data.currentUser?.userId;
                const topList = data.topContributors || [];
                const isUserInTop3 = topList.some(item => Number(item.userId) === Number(currentUserId));

                if (topList.length === 0) {
                    topContainer.innerHTML = `
                        <div class="text-center text-muted small py-3">
                            <i class="bi bi-people fs-3 d-block mb-1 text-secondary"></i>
                            Belum ada kontributor. Mulailah menambahkan tugas?
                        </div>`;
                } else {
                    topList.forEach(contributor => {
                        const defaultLogo = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'public/assets/img/logo.png';
                        const avatar = contributor.avatarUrl ? contributor.avatarUrl : defaultLogo;
                        const isSelf = Number(contributor.userId) === Number(currentUserId);
                        
                        const highlightClass = isSelf ? 'bg-primary bg-opacity-10 border border-primary border-opacity-25' : '';

                        topContainer.innerHTML += `
                            <div class="d-flex align-items-center justify-content-between gap-2 p-2 rounded-3 ${highlightClass}" style="overflow: hidden;">
                                <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1" style="overflow: hidden;">
                                    <img src="${avatar}" alt="${contributor.name}" class="rounded-circle border flex-shrink-0" style="width: 40px; height: 40px; object-fit: cover;" referrerpolicy="no-referrer">
                                    <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                                        <h6 class="mb-0 fw-semibold text-dark small text-truncate" title="${contributor.name}">${contributor.name}</h6>
                                        ${renderRoleElement(contributor.role)}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    ${isSelf ? '<span class="badge bg-secondary text-white" style="font-size: 0.55rem;">Akun Saya</span>' : ''}
                                    <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill fw-medium" style="font-size: 0.68rem;">
                                        ${contributor.total_tasks} Tugas
                                    </span>
                                </div>
                            </div>
                        `;
                    });
                }

                // Jika User TIDAK masuk Top 3, buat baris terpisah di bawah pemisah tipis
                if (!isUserInTop3 && data.currentUser) {
                    const myAvatar = data.currentUser.avatarUrl ? data.currentUser.avatarUrl : ((typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'public/assets/img/logo.png');
                    
                    topContainer.innerHTML += `
                        <hr class="my-2 border-secondary opacity-25">
                        <div class="d-flex align-items-center justify-content-between gap-2 p-2 bg-light rounded-3 border" style="overflow: hidden;">
                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1" style="overflow: hidden;">
                                <img src="${myAvatar}" alt="${data.currentUser.name}" class="rounded-circle border flex-shrink-0" style="width: 40px; height: 40px; object-fit: cover;" referrerpolicy="no-referrer">
                                <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                                    <h6 class="mb-0 fw-semibold text-dark small text-truncate" title="${data.currentUser.name}">${data.currentUser.name}</h6>
                                    ${renderRoleElement(data.currentUser.role)}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                <span class="badge bg-secondary text-white" style="font-size: 0.55rem;">Akun Saya</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill fw-medium" style="font-size: 0.68rem;">
                                    ${data.currentUser.totalTasks} Tugas
                                </span>
                            </div>
                        </div>
                    `;
                }
            }

            if (res.data.schedule) {
                const todayNameEl = document.getElementById('todayName');
                const tomorrowNameEl = document.getElementById('tomorrowName');
                
                if (todayNameEl) todayNameEl.textContent = res.data.schedule.todayName || '-';
                if (tomorrowNameEl) tomorrowNameEl.textContent = res.data.schedule.tomorrowName || '-';

                renderSchedule('today-courses-container', res.data.schedule.todayCourses || []);
                renderSchedule('tomorrow-courses-container', res.data.schedule.tomorrowCourses || []);
            }

            function renderSchedule(containerId, courses) {
                const container = document.getElementById(containerId);
                if (!container) return;

                // Jika data kosong, hilangkan spinner dan tampilkan status bebas jadwal
                if (!courses || courses.length === 0) {
                    container.innerHTML = `
                        <div class="card border-0 bg-light p-3 rounded-3 text-center" style="border: 2px dashed #cbd5e1 !important;">
                            <i class="bi bi-cup-hot fs-4 text-secondary mb-1"></i>
                            <p class="text-muted small fw-semibold mb-0">Bebas! Tidak ada jadwal kelas.</p>
                        </div>`;
                    return;
                }

                let html = '';
                courses.forEach(c => {
                    const timeDisplay = (c.startTime && c.endTime) 
                        ? `${c.startTime.substring(0, 5)} - ${c.endTime.substring(0, 5)}` 
                        : 'Waktu TBA';
                    const detailUrl = `${typeof BASE_URL !== 'undefined' ? BASE_URL : ''}tasks?courseId=${c.courseId}`;
                    const borderBg = c.backgroundColor || '#3b82f6';

                    html += `
                        <div class="card border-0 shadow-sm rounded-3 overflow-hidden" 
                            style="border-left: 5px solid ${borderBg} !important; cursor: pointer; transition: transform 0.2s;" 
                            onclick="window.location.href='${detailUrl}'" 
                            onmouseover="this.style.transform='translateX(4px)'" 
                            onmouseout="this.style.transform='translateX(0)'">
                            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">${c.courseTitle}</h6>
                                    <div class="small fw-medium text-secondary">
                                        <i class="bi bi-clock me-1 text-primary"></i>${timeDisplay}
                                        <span class="mx-1 text-muted">|</span>
                                        <i class="bi bi-geo-alt me-1 text-danger"></i>Kelas ${c.courseClass || '-'}
                                    </div>
                                </div>
                                <span class="badge ${c.courseType === 'Praktek' ? 'bg-warning text-dark' : 'bg-primary'} rounded-pill shadow-sm">${c.courseType || 'Teori'}</span>
                            </div>
                        </div>`;
                });

                container.innerHTML = html;
            }
        } else {
            handleFetchError();
        }
    } catch (error) {
        console.error("Gagal memuat data dashboard:", error);
        handleFetchError();
    }
}

function handleFetchError() {
    if (document.getElementById('quote-container')) {
        document.getElementById('quote-container').innerHTML = '"Tetap semangat menjalani perkuliahan!" &mdash; <strong>Overdose Team</strong>';
    }
    if (document.getElementById('stat-done')) document.getElementById('stat-done').textContent = '0';
    if (document.getElementById('stat-pending')) document.getElementById('stat-pending').textContent = '0';
    if (document.getElementById('stat-missed')) document.getElementById('stat-missed').textContent = '0';

    const taskContainer = document.getElementById('pending-tasks-list');
    if (taskContainer) {
        taskContainer.innerHTML = `
            <div class="text-center text-muted small py-4">
                <i class="bi bi-emoji-smile fs-3 d-block mb-1 text-success"></i>
                Tidak ada tugas! Yey!
            </div>`;
    }

    const topContainer = document.getElementById('top-contributors-list');
    if (topContainer) {
        topContainer.innerHTML = `
            <div class="text-center text-muted small py-4">
                <i class="bi bi-people fs-3 d-block mb-1 text-secondary"></i>
                Belum ada kontributor. Mulailah menambahkan tugas?
            </div>`;
    }
}