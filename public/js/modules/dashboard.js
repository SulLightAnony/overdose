document.addEventListener("DOMContentLoaded", () => {
    fetchDashboardData();
    checkAttendanceWindow();
    window.setInterval(() => {
        updateDashboardAttendanceBadges();
        checkAttendanceWindow();
    }, 60000);
});

let dashboardSchedule = null;
let serverClockOffset = 0;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

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

function renderSchedule(containerId, courses, isToday = false) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!Array.isArray(courses) || courses.length === 0) {
        container.innerHTML = `
            <div class="card border-0 bg-light p-3 rounded-3 text-center" style="border: 2px dashed #cbd5e1 !important;">
                <i class="bi bi-calendar-x fs-4 text-secondary mb-1"></i>
                <p class="text-muted small fw-semibold mb-0">Tidak ada data jadwal.</p>
            </div>`;
        return;
    }

    let html = '';
    courses.forEach(c => {
        const timeDisplay = (c.startTime && c.endTime)
            ? `${c.startTime.substring(0, 5)} - ${c.endTime.substring(0, 5)}`
            : 'Waktu TBA';
        const detailUrl = `${typeof BASE_URL !== 'undefined' ? BASE_URL : ''}course-detail?id=${c.courseId}&from=dashboard`;
        const borderBg = c.backgroundColor || '#3b82f6';
        const isAttendanceActive = isToday && isCourseAttendanceActive(c);

        html += `
            <a href="${detailUrl}" class="card border-0 shadow-sm rounded-3 overflow-hidden text-decoration-none"
                style="border-left: 5px solid ${borderBg} !important; cursor: pointer; transition: transform 0.2s; color: inherit;"
                onmouseover="this.style.transform='translateX(4px)'"
                onmouseout="this.style.transform='translateX(0)'">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">${c.courseTitle}</h6>
                        ${isAttendanceActive ? '<span class="badge bg-success text-white mb-1">Jam Aktif Absensi</span>' : ''}
                        <div class="small fw-medium text-secondary">
                            <i class="bi bi-clock me-1 text-primary"></i>${timeDisplay}
                            <span class="mx-1 text-muted">|</span>
                            <i class="bi bi-geo-alt me-1 text-danger"></i>Kelas ${c.courseClass || '-'}</div>
                    </div>
                    <span class="badge ${c.courseType === 'Praktek' ? 'bg-warning text-dark' : 'bg-primary'} rounded-pill shadow-sm">${c.courseType || 'Teori'}</span>
                </div>
            </div>`;
    });

    container.innerHTML = html;
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

            const pendingCountBadge = document.getElementById('pending-tasks-count-badge');
            if (pendingCountBadge) {
                pendingCountBadge.textContent = data.stats.pending ?? 0;
            }

            // 3. Render Daftar Tugas Mendatang (Maksimal 3 Tugas)
            if (taskContainer) {
                taskContainer.innerHTML = '';
                const pendingList = (data.pendingTasks || []).slice(0, 3);
                const now = new Date();

                if (pendingList.length === 0) {
                    taskContainer.innerHTML = `
                        <div class="text-center text-muted small py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                            Tidak ada tugas mendatang.
                        </div>`;
                } else {
                    pendingList.forEach(task => {
                        const baseUrlPath = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
                        const taskId = task.taskId;
                        const taskTitle = task.taskTitle || task.title || 'Tugas';
                        const courseTitle = task.courseTitle || 'Mata Kuliah';
                        const courseId = task.courseId || 0;
                        const rawDueDate = task.dueDate || task.deadline || '';
                        const dueDate = rawDueDate ? new Date(rawDueDate.replace(/-/g, '/')) : null;

                        // Tipe Mata Kuliah (Teori / Praktek) - TANPA IKON (100% Identik dengan Warna Jadwal Kuliah)
                        const courseTypeStr = (task.courseType || 'Teori').trim();
                        const isPraktek = courseTypeStr.toLowerCase() === 'praktek';
                        const typeBadgeClass = isPraktek ? 'bg-warning text-dark' : 'bg-primary text-white';
                        const courseTypeBadgeHtml = `<span class="badge ${typeBadgeClass} rounded-pill px-2 py-0.5 align-middle" style="font-size: 0.68rem; font-weight: 500;">${escapeHtml(courseTypeStr)}</span>`;

                        // Badge tipe spesifik jika bukan 'Tugas' (misal: Kuis, UTS, UAS, Praktikum)
                        let taskTypeBadgeHtml = '';
                        if (task.taskType && task.taskType.trim().toLowerCase() !== 'tugas') {
                            taskTypeBadgeHtml = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-0.5 align-middle" style="font-size: 0.68rem; font-weight: 600;">${escapeHtml(task.taskType)}</span>`;
                        }

                        // Sisa Waktu Teks Berwarna Tanpa Background atau Ikon
                        let countdownTextHtml = '';
                        if (dueDate) {
                            const diffMs = dueDate.getTime() - now.getTime();
                            const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
                            const remainingText = formatRemainingTime(diffMs);

                            if (totalHours <= 24) {
                                countdownTextHtml = `<small class="text-danger fw-bold ms-1" style="font-size: 0.72rem;">(${escapeHtml(remainingText)})</small>`;
                            } else if (totalHours <= 168) {
                                countdownTextHtml = `<small class="fw-semibold ms-1" style="color: #d97706; font-size: 0.72rem;">(${escapeHtml(remainingText)})</small>`;
                            } else {
                                countdownTextHtml = `<small class="text-success fw-medium ms-1" style="font-size: 0.72rem;">(${escapeHtml(remainingText)})</small>`;
                            }
                        }

                        // Link Anchor ke Detail Course (TANPA IKON di samping judul matkul)
                        const courseDetailUrl = `${baseUrlPath}course-detail?id=${courseId}&from=dashboard`;
                        const courseLinkHtml = `<a href="${courseDetailUrl}" onclick="event.stopPropagation();" class="text-decoration-none text-dark fw-bold me-2 hover-primary align-middle" style="font-size: 0.78rem;">${escapeHtml(courseTitle)}</a>`;

                        const detailUrl = `${baseUrlPath}task-detail?id=${taskId}&from=dashboard`;

                        taskContainer.innerHTML += `
                            <div onclick="window.location.href='${detailUrl}'"
                                 class="card border shadow-sm rounded-3 text-dark overflow-hidden task-card-item mb-1"
                                 style="cursor: pointer; transition: transform 160ms ease, box-shadow 160ms ease;"
                                 onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.13)'; this.style.borderColor='rgba(99,102,241,0.35)';"
                                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow=''; this.style.borderColor='';">
                                <div class="card-body p-2.5 px-3 p-md-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center gap-1.5 mb-1 flex-wrap">
                                            ${courseLinkHtml}
                                            ${courseTypeBadgeHtml}
                                            ${taskTypeBadgeHtml}
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size: 0.85rem;">${escapeHtml(taskTitle)}</h6>
                                        <div class="d-flex align-items-center flex-wrap gap-1">
                                            <small class="text-muted align-middle" style="font-size: 0.72rem;">
                                                <i class="bi bi-calendar-event me-1"></i>${escapeHtml(formatDateTime(rawDueDate))}
                                            </small>
                                            ${countdownTextHtml}
                                        </div>
                                    </div>
                                    <div class="ms-2 text-secondary flex-shrink-0">
                                        <i class="bi bi-chevron-right fs-6"></i>
                                    </div>
                                </div>
                            </div>`;
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
                                        ${contributor.total_tasks} Poin
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
                dashboardSchedule = res.data.schedule;
                serverClockOffset = new Date(res.data.schedule.serverNow).getTime() - Date.now();
                updateDashboardAttendanceBadges();
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

    renderSchedule('today-courses-container', []);
    renderSchedule('tomorrow-courses-container', []);
}

function isCourseAttendanceActive(course) {
    if (!course.startTime || !course.endTime) return false;
    const timeParts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23'
    }).format(new Date(Date.now() + serverClockOffset)).split(':').map(Number);
    const currentMinutes = timeParts[0] * 60 + timeParts[1];
    const [startHour, startMinute] = course.startTime.split(':').map(Number);
    const [endHour, endMinute] = course.endTime.split(':').map(Number);
    const startMinutes = startHour * 60 + startMinute;
    const endMinutes = endHour * 60 + endMinute;
    return currentMinutes >= startMinutes - 30 && currentMinutes < endMinutes;
}

function updateDashboardAttendanceBadges() {
    if (!dashboardSchedule) return;
    renderSchedule('today-courses-container', dashboardSchedule.todayCourses || [], true);
    renderSchedule('tomorrow-courses-container', dashboardSchedule.tomorrowCourses || []);
}

async function checkAttendanceWindow() {
    if (typeof BASE_URL === 'undefined') return;
    try {
        await fetch(`${BASE_URL}app/api/schedule.php?action=attendance_check`, { headers: { Accept: 'application/json' } });
    } catch (error) {
        console.error('Attendance notification check failed:', error);
    }
}