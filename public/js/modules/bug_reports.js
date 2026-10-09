document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bugReportForm');
    const list = document.getElementById('bugReportsList');
    const alertBox = document.getElementById('bugReportAlert');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const reportStatus = {open:'Baru', in_progress:'Diproses', resolved:'Selesai', closed:'Ditutup'};
    const notify = (message, type='success') => { if (window.appToast) window.appToast(message, type); else alertBox.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`; };

    async function request(url, options) {
        const response = await fetch(url, options);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan gagal.');
        return data;
    }

    async function loadReports() {
        if (!list || !CAN_REVIEW_BUG_REPORTS) return;
        try {
            const result = await request(`${BASE_URL}app/api/bug_reports.php?action=list`);
            const reports = result.data.reports || [];
            list.innerHTML = reports.length ? reports.map(report => `
                <article class="card border-0 shadow-sm"><div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div><strong>${escapeHtml(report.userName || 'Pengguna Nonaktif')}</strong><small class="d-block text-muted">${escapeHtml(report.created_at)}</small></div>
                        <div class="d-flex gap-2 align-items-center">
                            <select class="form-select form-select-sm" data-status-id="${Number(report.id)}" aria-label="Status report">
                                ${Object.entries(reportStatus).map(([value,label]) => `<option value="${value}" ${report.status === value ? 'selected' : ''}>${label}</option>`).join('')}
                            </select>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-delete-report="${Number(report.id)}">Hapus</button>
                        </div>
                    </div>
                    <p class="mb-0 text-break" style="white-space:pre-wrap">${escapeHtml(report.description)}</p>
                </div></article>`).join('') : '<div class="text-center text-muted py-5">Belum ada report.</div>';
        } catch (error) {
            list.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        }
    }

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('submitBugReport');
        button.disabled = true;
        try {
            const data = new FormData(form);
            data.append('action', 'create');
            await request(`${BASE_URL}app/api/bug_reports.php`, {method:'POST', body:data});
            form.reset();
            notify('Laporan berhasil dikirim.');
            await loadReports();
        } catch (error) { notify(error.message, 'danger'); }
        finally { button.disabled = false; }
    });

    list?.addEventListener('change', async event => {
        if (!event.target.matches('[data-status-id]')) return;
        try {
            await request(`${BASE_URL}app/api/bug_reports.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'update_status', id:event.target.dataset.statusId, status:event.target.value})});
            notify('Status report diperbarui.');
        } catch (error) { notify(error.message, 'danger'); await loadReports(); }
    });
    list?.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-report]');
        if (!button || !await window.appConfirm('Hapus laporan bug ini?')) return;
        try {
            await request(`${BASE_URL}app/api/bug_reports.php`, {method:'DELETE', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:button.dataset.deleteReport})});
            notify('Laporan dihapus.');
            await loadReports();
        } catch (error) { notify(error.message, 'danger'); }
    });
    loadReports();
});
