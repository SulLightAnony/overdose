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
    try {
        const response = await fetch(BASE_URL + 'app/api/dashboard.php');
        const res = await response.json();

        if (res.success) {
            const data = res.data;

            // 1. Render Quote
            document.getElementById('quote-container').innerHTML = 
                `"${data.quote.quote}" &mdash; <strong>${data.quote.author}</strong>`;

            // 2. Render Angka Statistik
            document.getElementById('stat-done').textContent = data.stats.done;
            document.getElementById('stat-pending').textContent = data.stats.pending;
            document.getElementById('stat-missed').textContent = data.stats.missed;

            // 3. Render Daftar Tugas
            const taskContainer = document.getElementById('pending-tasks-list');
            taskContainer.innerHTML = '';
            
            if (data.pendingTasks.length === 0) {
                taskContainer.innerHTML = `
                    <div class="text-center text-muted small py-4">
                        <i class="bi bi-emoji-smile fs-3 d-block mb-1 text-success"></i>
                        Tidak ada tugas! Yey!
                    </div>`;
            } else {
                data.pendingTasks.forEach(task => {
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
                            <a href="${BASE_URL}task/detail?id=${task.taskId}" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold">Detail</a>
                        </div>
                    `;
                });
            }

            // 4. Render Top Contributors (Max 3)
            const topContainer = document.getElementById('top-contributors-list');
            topContainer.innerHTML = '';

            if (data.topContributors.length === 0) {
                topContainer.innerHTML = `
                    <div class="text-center text-muted small py-4">
                        <i class="bi bi-people fs-3 d-block mb-1 text-secondary"></i>
                        Belum ada kontributor. Mulailah menambahkan tugas?
                    </div>`;
            } else {
                data.topContributors.forEach(contributor => {
                    const avatar = contributor.avatarUrl ? contributor.avatarUrl : BASE_URL + 'public/assets/img/logo.png';
                    topContainer.innerHTML += `
                        <div class="d-flex align-items-center gap-3">
                            <img src="${avatar}" alt="${contributor.name}" class="rounded-circle border" style="width: 42px; height: 42px; object-fit: cover;">
                            <div class="w-100">
                                <h6 class="mb-0 fw-semibold text-dark small d-flex align-items-center">
                                    ${contributor.name} ${getRoleBadge(contributor.role)}
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
        }
    } catch (error) {
        console.error("Gagal memuat dashboard:", error);
    }
}