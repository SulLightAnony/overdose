document.addEventListener('DOMContentLoaded', async () => {
    const list = document.getElementById('globalTasksList');
    const alertBox = document.getElementById('globalTasksAlert');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    try {
        const response = await fetch(`${BASE_URL}app/api/tasks.php?action=global_list&filter=${encodeURIComponent(GLOBAL_TASK_FILTER)}`);
        const text = await response.text();
        let result;
        try { result = JSON.parse(text); } catch (error) { throw new Error('Server mengembalikan respons yang bukan JSON.'); }
        if (!response.ok || !result.success) throw new Error(result.message || 'Gagal memuat tugas.');
        const tasks = result.data.tasks || [];
        list.innerHTML = tasks.length ? tasks.map(task => `<a class="d-flex justify-content-between align-items-center p-3 border rounded-3 text-decoration-none text-dark" href="${BASE_URL}task-detail?id=${task.taskId}"><span><strong>${escapeHtml(task.taskTitle)}</strong><small class="d-block text-muted">${escapeHtml(task.courseTitle)} | ${escapeHtml(task.taskType)}</small></span><small class="text-danger">${escapeHtml(task.dueDate)}</small></a>`).join('') : '<div class="text-center text-muted py-5">Tidak ada tugas pada filter ini.</div>';
    } catch (error) {
        alertBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
    }
});
