document.addEventListener("DOMContentLoaded", () => {
    fetchDashboardData();
});

function getRoleBadge(role) {
    if (role === 'Primordial' || role === 'Sepuh') {
        return `<i class="bi bi-patch-check-fill text-warning ms-1" title="${role}"></i>`;
    }
    return '';
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
                        
                        // Highlight khusus jika user saat ini masuk ke Top 3
                        const highlightClass = isSelf ? 'bg-primary bg-opacity-10 border border-primary border-opacity-25 p-2 rounded-3' : '';

                        topContainer.innerHTML += `
                            <div class="d-flex align-items-center gap-3 ${highlightClass}">
                                <img src="${avatar}" alt="${contributor.name}" class="rounded-circle border" style="width: 42px; height: 42px; object-fit: cover;">
                                <div class="w-100">
                                    <h6 class="mb-0 fw-semibold text-dark small d-flex align-items-center">
                                        ${contributor.name} ${getRoleBadge(contributor.role)} ${isSelf ? '<span class="badge bg-primary text-white ms-auto" style="font-size:0.6rem">Kamu</span>' : ''}
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center w-100 mt-1">
                                        <small class="text-muted" style="font-size: 0.7rem;">${contributor.role}</small>
                                        <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill fw-medium" style="font-size: 0.68rem;">
                                            ${contributor.total_tasks} Tugas
                                        </span>
                                    </div>
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
                        <div class="d-flex align-items-center gap-3 p-2 bg-light rounded-3 border">
                            <img src="${myAvatar}" alt="${data.currentUser.name}" class="rounded-circle border" style="width: 42px; height: 42px; object-fit: cover;">
                            <div class="w-100">
                                <h6 class="mb-0 fw-semibold text-dark small d-flex align-items-center">
                                    ${data.currentUser.name} ${getRoleBadge(data.currentUser.role)}
                                    <span class="badge bg-secondary text-white ms-auto" style="font-size:0.6rem">Akun Saya</span>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center w-100 mt-1">
                                    <small class="text-muted" style="font-size: 0.7rem;">${data.currentUser.role}</small>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill fw-medium" style="font-size: 0.68rem;">
                                        ${data.currentUser.totalTasks} Tugas
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                }
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