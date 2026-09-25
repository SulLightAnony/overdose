document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('usersTableBody');
    const search = document.getElementById('searchUser');
    const alertBox = document.getElementById('userManagementAlert');
    let users = [];

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const showAlert = message => { if (alertBox) alertBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(message)}</div>`; };

    async function request(url, options) {
        const response = await fetch(url, options);
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch (error) { throw new Error('Server mengembalikan respons yang bukan JSON.'); }
        if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan gagal.');
        return data;
    }

    function render(list) {
        if (!body) return;
        body.innerHTML = list.length ? list.map(user => {
            const locked = user.userStatus === 'blocked' && user.blockedByRole === 'Primordial';
            const action = user.userStatus === 'blocked'
                ? `<button class="btn btn-sm btn-success" data-action="active" data-id="${user.userId}" ${locked ? 'disabled' : ''}>Buka Blokir</button>`
                : `<button class="btn btn-sm btn-danger" data-action="blocked" data-id="${user.userId}">Blokir</button>`;
            return `<tr><td><strong>${escapeHtml(user.userName)}</strong><small class="d-block text-muted">${escapeHtml(user.emailAddress)}</small></td><td><select class="form-select form-select-sm" data-role data-id="${user.userId}" style="max-width:130px"><option ${user.roleLevel === 'Keroco' ? 'selected' : ''}>Keroco</option><option ${user.roleLevel === 'Sepuh' ? 'selected' : ''}>Sepuh</option><option ${user.roleLevel === 'Primordial' ? 'selected' : ''}>Primordial</option></select></td><td>${escapeHtml(user.userStatus)}${locked ? '<small class="d-block text-muted">Dikunci Primordial</small>' : ''}</td><td class="text-end">${action}</td></tr>`;
        }).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada pengguna.</td></tr>';
    }

    async function load() {
        try { const data = await request(`${BASE_URL}app/api/configurations.php?action=list_users`); users = data.data.users || []; render(users); }
        catch (error) { showAlert(error.message); }
    }

    if (body) {
        body.addEventListener('change', async event => {
            if (!event.target.matches('[data-role]')) return;
            try { await request(`${BASE_URL}app/api/configurations.php`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'update_user_role', targetUserId:event.target.dataset.id, newRole:event.target.value}) }); await load(); }
            catch (error) { showAlert(error.message); await load(); }
        });
        body.addEventListener('click', async event => {
            if (!event.target.dataset.action) return;
            try { await request(`${BASE_URL}app/api/configurations.php`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle_user_block', targetUserId:event.target.dataset.id, newStatus:event.target.dataset.action}) }); await load(); }
            catch (error) { showAlert(error.message); }
        });
    }
    if (search) {
        search.addEventListener('input', () => { const query = search.value.toLowerCase(); render(users.filter(user => `${user.userName} ${user.emailAddress}`.toLowerCase().includes(query))); });
    }
    load();
});
