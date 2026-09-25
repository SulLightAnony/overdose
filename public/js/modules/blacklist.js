document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('blacklistTableBody');
    const form = document.getElementById('formAddBlacklist');
    const alertBox = document.getElementById('blacklistAlert');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const showAlert = (message, type = 'danger') => { if (alertBox) alertBox.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`; };
    async function request(url, options) { const response = await fetch(url, options); const text = await response.text(); let data; try { data = JSON.parse(text); } catch (error) { throw new Error('Server mengembalikan respons yang bukan JSON.'); } if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan gagal.'); return data; }
    function render(items) { if (!body) return; body.innerHTML = items.length ? items.map(item => { const locked = CURRENT_USER_ROLE === 'Sepuh' && item.blockedByRole === 'Primordial'; return `<tr><td>${escapeHtml(item.emailAddress)}</td><td>${escapeHtml(item.reasonDescription || '-')}</td><td>${escapeHtml(item.addedByName || 'Sistem')}<small class="d-block text-muted">${escapeHtml(item.blockedByRole || '')}</small></td><td class="text-end">${locked ? '<span class="text-muted small">Dikunci Primordial</span>' : `<button class="btn btn-sm btn-outline-danger" data-id="${item.blacklistId}">Hapus</button>`}</td></tr>`; }).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada blacklist.</td></tr>'; }
    async function load() { try { const data = await request(`${BASE_URL}app/api/configurations.php?action=list_blacklist`); render(data.data.blacklist || []); } catch (error) { showAlert(error.message); } }
    if (form) {
        form.addEventListener('submit', async event => { event.preventDefault(); const button = document.getElementById('btnSubmitBlacklist'); if (button) button.disabled = true; try { await request(`${BASE_URL}app/api/configurations.php`, {method:'POST', body:new FormData(form)}); const modalEl = document.getElementById('addBlacklistModal'); if (modalEl) { const modalInst = bootstrap.Modal.getInstance(modalEl); if (modalInst) modalInst.hide(); } form.reset(); await load(); } catch (error) { showAlert(error.message); } finally { if (button) button.disabled = false; } });
    }
    if (body) {
        body.addEventListener('click', async event => { if (!event.target.dataset.id || !window.confirm('Hapus email ini dari blacklist?')) return; try { await request(`${BASE_URL}app/api/configurations.php`, {method:'DELETE', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'remove_blacklist', blacklistId:event.target.dataset.id})}); await load(); } catch (error) { showAlert(error.message); } });
    }
    load();
});
