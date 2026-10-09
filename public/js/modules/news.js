document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('newsList');
    const form = document.getElementById('newsForm');
    const modalElement = document.getElementById('newsModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const alertBox = document.getElementById('newsAlert');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const notify = (message, type='success') => { if (window.appToast) window.appToast(message, type); else alertBox.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`; };
    const canManageAll = ['Sepuh','Primordial'].includes(CURRENT_NEWS_ROLE);

    async function request(url, options) {
        const response = await fetch(url, options);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan gagal.');
        return data;
    }

    function resetForm() {
        form.reset();
        document.getElementById('newsId').value = '';
        document.getElementById('newsModalTitle').textContent = 'Tulis Berita';
        document.getElementById('submitNews').textContent = 'Terbitkan';
    }

    async function loadNews() {
        try {
            const result = await request(`${BASE_URL}app/api/news.php?action=list`);
            const articles = result.data.news || [];
            list.innerHTML = articles.length ? articles.map(article => {
                const isOwner = Number(article.user_id) === CURRENT_NEWS_USER_ID;
                const canManage = isOwner || canManageAll;
                return `<div class="col-md-6 col-xl-4"><article class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-bold">${escapeHtml(article.title)}</h5>
                        <p class="text-muted small mb-2">${escapeHtml(article.userName || 'Pengguna Nonaktif')} | ${escapeHtml(article.created_at)}</p>
                        <div class="text-break flex-grow-1" style="white-space:pre-wrap">${escapeHtml(article.content)}</div>
                        ${canManage ? `<div class="d-flex gap-2 mt-3 pt-3 border-top"><button type="button" class="btn btn-sm btn-outline-primary" data-edit-news="${Number(article.id)}" data-title="${escapeHtml(article.title)}">Edit</button><button type="button" class="btn btn-sm btn-outline-danger" data-delete-news="${Number(article.id)}">Hapus</button></div>` : ''}
                        <button type="button" class="btn btn-sm btn-link align-self-start px-0 mt-2" data-share-url="${BASE_URL}news#news-${Number(article.id)}">Bagikan</button>
                    </div></article></div>`;
            }).join('') : '<div class="col-12 text-center text-muted py-5">Belum ada berita.</div>';
        } catch (error) { list.innerHTML = `<div class="col-12"><div class="alert alert-danger">${escapeHtml(error.message)}</div></div>`; }
    }

    document.getElementById('openNewsForm').addEventListener('click', () => { resetForm(); modal.show(); });
    list.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit-news]');
        if (edit) {
            document.getElementById('newsId').value = edit.dataset.editNews;
            document.getElementById('newsTitle').value = edit.dataset.title;
            const article = edit.closest('article');
            document.getElementById('newsContent').value = article.querySelector('.text-break').textContent;
            document.getElementById('newsModalTitle').textContent = 'Edit Berita';
            document.getElementById('submitNews').textContent = 'Simpan Perubahan';
            modal.show();
            return;
        }
        const remove = event.target.closest('[data-delete-news]');
        if (remove && await window.appConfirm('Hapus berita ini?')) {
            try { await request(`${BASE_URL}app/api/news.php`, {method:'DELETE', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:remove.dataset.deleteNews})}); notify('Berita dihapus.'); await loadNews(); }
            catch (error) { notify(error.message, 'danger'); }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('submitNews');
        button.disabled = true;
        const id = document.getElementById('newsId').value;
        const payload = {action: id ? 'update' : 'create', id, title: document.getElementById('newsTitle').value, content: document.getElementById('newsContent').value};
        try { await request(`${BASE_URL}app/api/news.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); modal.hide(); resetForm(); notify(id ? 'Berita diperbarui.' : 'Berita diterbitkan.'); await loadNews(); }
        catch (error) { notify(error.message, 'danger'); }
        finally { button.disabled = false; }
    });
    loadNews();
});
