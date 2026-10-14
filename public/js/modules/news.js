document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('newsList');
    const form = document.getElementById('newsForm');
    const modalElement = document.getElementById('newsModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const alertBox = document.getElementById('newsAlert');

    const imageInput = document.getElementById('newsImageInput');
    const imagePreviewContainer = document.getElementById('newsImagePreviewContainer');
    const imagePreviewImg = document.getElementById('newsImagePreviewImg');
    const imagePreviewName = document.getElementById('newsImagePreviewName');
    const imagePreviewSize = document.getElementById('newsImagePreviewSize');
    const btnRemoveImage = document.getElementById('btnRemoveNewsImage');
    const removeImageFlag = document.getElementById('newsRemoveImage');

    const imagePreviewModalEl = document.getElementById('imagePreviewModal');
    const imagePreviewModal = imagePreviewModalEl ? bootstrap.Modal.getOrCreateInstance(imagePreviewModalEl) : null;
    const modalImg = document.getElementById('imagePreviewImg');
    const modalImgTitle = document.getElementById('imagePreviewTitle');

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const notify = (message, type = 'success') => {
        if (window.appToast) {
            window.appToast(message, type);
        } else if (alertBox) {
            alertBox.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    };

    const canManageAll = ['Sepuh', 'Primordial'].includes(CURRENT_NEWS_ROLE);
    const defaultAvatar = `${BASE_URL}public/assets/img/logo.png`;

    function renderRoleBadge(role) {
        if (!role) return '';
        const lower = role.toLowerCase();
        if (lower === 'primordial') {
            return `<span class="badge" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.65rem; font-weight: 600;">Primordial</span>`;
        }
        if (lower === 'sepuh') {
            return `<span class="badge bg-secondary text-white" style="font-size: 0.65rem;">Sepuh</span>`;
        }
        if (lower === 'keroco') {
            return `<span class="text-muted" style="font-size: 0.72rem;">Keroco</span>`;
        }
        return '';
    }

    function formatDate(rawDate) {
        if (!rawDate) return '-';
        try {
            const date = new Date(rawDate.replace(/-/g, '/'));
            return date.toLocaleString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return rawDate;
        }
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function getThumbnailUrl(imageUrl, width = 450) {
        if (!imageUrl) return '';
        if (imageUrl.includes('drive.google.com/thumbnail')) {
            if (/sz=[ws]\d+/i.test(imageUrl)) {
                return imageUrl.replace(/sz=[ws]\d+/i, `sz=w${width}`);
            }
            return imageUrl + `&sz=w${width}`;
        }
        if (imageUrl.includes('lh3.googleusercontent.com/d/')) {
            return imageUrl.replace(/=[ws]\d+/i, '') + `=w${width}`;
        }
        return imageUrl;
    }

    function getFullImageUrl(imageUrl) {
        if (!imageUrl) return '';
        if (imageUrl.includes('drive.google.com/thumbnail')) {
            if (/sz=[ws]\d+/i.test(imageUrl)) {
                return imageUrl.replace(/sz=[ws]\d+/i, 'sz=w1600');
            }
            return imageUrl + '&sz=w1600';
        }
        return imageUrl;
    }

    function initLazyImages(container = document) {
        const lazyImages = container.querySelectorAll('img.news-lazy-img[data-src]');
        if (!lazyImages.length) return;

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('data-src');
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            img.onload = () => {
                                img.style.opacity = '1';
                                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                                if (placeholder) placeholder.remove();
                            };
                            img.onerror = () => {
                                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                                if (placeholder) {
                                    placeholder.style.animation = 'none';
                                    placeholder.innerHTML = '<span class="small text-muted opacity-50"><i class="bi bi-exclamation-circle me-1"></i>Gagal memuat</span>';
                                }
                            };
                        }
                        obs.unobserve(img);
                    }
                });
            }, {
                rootMargin: '100px 0px',
                threshold: 0.01
            });

            lazyImages.forEach(img => observer.observe(img));
        } else {
            lazyImages.forEach(img => {
                img.src = img.getAttribute('data-src');
                img.style.opacity = '1';
                const placeholder = img.parentElement?.querySelector('.news-image-placeholder');
                if (placeholder) placeholder.remove();
            });
        }
    }

    function resetForm() {
        form.reset();
        document.getElementById('newsId').value = '';
        document.getElementById('newsModalTitle').textContent = 'Tulis Berita';
        document.getElementById('submitNews').innerHTML = 'Terbitkan';
        if (removeImageFlag) removeImageFlag.value = '0';
        if (imagePreviewContainer) {
            imagePreviewContainer.classList.add('d-none');
            imagePreviewContainer.classList.remove('d-flex');
        }
        if (imagePreviewImg) imagePreviewImg.src = '';
    }

    // Image Input Selection & Validation
    if (imageInput) {
        imageInput.addEventListener('change', () => {
            const file = imageInput.files[0];
            if (!file) return;

            // Validate MIME type
            if (!file.type.startsWith('image/')) {
                notify('Hanya berkas gambar (JPG, PNG, WEBP) yang diizinkan.', 'danger');
                imageInput.value = '';
                return;
            }

            // Validate Size: 1 MB max
            if (file.size > 1024 * 1024) {
                notify('Ukuran gambar tidak boleh melebihi 1 MB.', 'danger');
                imageInput.value = '';
                return;
            }

            if (removeImageFlag) removeImageFlag.value = '0';
            const reader = new FileReader();
            reader.onload = (e) => {
                if (imagePreviewImg) imagePreviewImg.src = e.target.result;
                if (imagePreviewName) imagePreviewName.textContent = file.name;
                if (imagePreviewSize) imagePreviewSize.textContent = formatFileSize(file.size);
                if (imagePreviewContainer) {
                    imagePreviewContainer.classList.remove('d-none');
                    imagePreviewContainer.classList.add('d-flex');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    if (btnRemoveImage) {
        btnRemoveImage.addEventListener('click', () => {
            if (imageInput) imageInput.value = '';
            if (removeImageFlag) removeImageFlag.value = '1';
            if (imagePreviewContainer) {
                imagePreviewContainer.classList.add('d-none');
                imagePreviewContainer.classList.remove('d-flex');
            }
            if (imagePreviewImg) imagePreviewImg.src = '';
        });
    }

    async function loadNews() {
        try {
            const response = await fetch(`${BASE_URL}app/api/news.php?action=list`);
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Gagal memuat berita.');

            const articles = result.data?.news || [];
            if (!articles.length) {
                list.innerHTML = `
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-newspaper fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <p class="mb-0 small">Belum ada berita yang diterbitkan saat ini.</p>
                    </div>`;
                return;
            }

            list.innerHTML = articles.map(article => {
                const isOwner = Number(article.user_id) === CURRENT_NEWS_USER_ID;
                const canManage = isOwner || canManageAll;
                const authorName = article.userName || 'Pengguna Nonaktif';
                const authorAvatar = article.avatarUrl || defaultAvatar;
                const authorRole = article.roleLevel || 'Keroco';
                const dateStr = formatDate(article.created_at);
                const hasImage = Boolean(article.image_url);
                const thumbUrl = hasImage ? getThumbnailUrl(article.image_url, 450) : '';
                const fullImageUrl = hasImage ? getFullImageUrl(article.image_url) : '';

                return `
                    <div class="col-md-6 col-xl-4" id="news-${Number(article.id)}">
                        <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column news-card" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
                            ${hasImage ? `
                                <div class="position-relative overflow-hidden d-flex align-items-center justify-content-center p-2" style="height: 185px; background-color: #f8fafc; border-bottom: 1px solid #f1f5f9; cursor: pointer;" data-preview-image="${escapeHtml(fullImageUrl)}" data-preview-title="${escapeHtml(article.title)}" title="Klik untuk memperbesar">
                                    <div class="news-image-placeholder position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: newsShimmer 1.5s infinite;">
                                        <i class="bi bi-image fs-3 opacity-25"></i>
                                    </div>
                                    <img data-src="${escapeHtml(thumbUrl)}" alt="${escapeHtml(article.title)}" class="w-100 h-100 news-lazy-img" style="object-fit: contain; max-height: 100%; max-width: 100%; opacity: 0; transition: opacity 0.3s ease;" referrerpolicy="no-referrer">
                                    <div class="position-absolute top-0 end-0 m-2" style="z-index: 2;">
                                        <span class="badge bg-dark bg-opacity-75 rounded-pill px-2.5 py-1 small" style="backdrop-filter: blur(4px); font-size: 0.72rem;">
                                            <i class="bi bi-zoom-in me-1"></i>Perbesar
                                        </span>
                                    </div>
                                </div>
                            ` : ''}

                            <div class="card-body p-4 d-flex flex-column flex-grow-1">
                                <!-- Author Header -->
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <img src="${escapeHtml(authorAvatar)}" alt="${escapeHtml(authorName)}" class="rounded-circle border flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;" referrerpolicy="no-referrer" onerror="this.src='${defaultAvatar}'">
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;" title="${escapeHtml(authorName)}">
                                            ${escapeHtml(authorName)}
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            ${renderRoleBadge(authorRole)}
                                            <span class="text-muted" style="font-size: 0.72rem;">
                                                <i class="bi bi-clock me-1"></i>${escapeHtml(dateStr)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- News Title -->
                                <h5 class="card-title fw-bold text-dark mb-2 fs-6 text-break">
                                    ${escapeHtml(article.title)}
                                </h5>

                                <!-- News Content -->
                                <div class="text-secondary small text-break flex-grow-1 mb-3 news-content" style="white-space: pre-wrap; line-height: 1.6;">${escapeHtml(article.content)}</div>

                                <!-- Card Footer / Action Buttons -->
                                <div class="d-flex align-items-center justify-content-between pt-3 border-top border-light mt-auto flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-share-news="${Number(article.id)}" data-title="${escapeHtml(article.title)}">
                                        <i class="bi bi-share" style="font-size: 0.75rem;"></i>Bagikan
                                    </button>

                                    ${canManage ? `
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-dark rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-edit-news="${Number(article.id)}" data-title="${escapeHtml(article.title)}" data-image="${escapeHtml(article.image_url || '')}">
                                                <i class="bi bi-pencil-square" style="font-size: 0.75rem;"></i>Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;" data-delete-news="${Number(article.id)}">
                                                <i class="bi bi-trash3" style="font-size: 0.75rem;"></i>Hapus
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </article>
                    </div>`;
            }).join('');
            initLazyImages(list);
        } catch (error) {
            list.innerHTML = `<div class="col-12"><div class="alert alert-danger rounded-3">${escapeHtml(error.message)}</div></div>`;
        }
    }

    document.getElementById('openNewsForm')?.addEventListener('click', () => {
        resetForm();
        modal.show();
    });

    list.addEventListener('click', async event => {
        // Image preview zoom modal
        const previewEl = event.target.closest('[data-preview-image]');
        if (previewEl && imagePreviewModal) {
            const imgUrl = previewEl.dataset.previewImage;
            const imgTitle = previewEl.dataset.previewTitle || 'Pratinjau Gambar';
            if (modalImg) modalImg.src = imgUrl;
            if (modalImgTitle) modalImgTitle.textContent = imgTitle;
            imagePreviewModal.show();
            return;
        }

        // Share button
        const shareBtn = event.target.closest('[data-share-news]');
        if (shareBtn) {
            const shareUrl = `${BASE_URL}news#news-${shareBtn.dataset.shareNews}`;
            try {
                await navigator.clipboard.writeText(shareUrl);
                notify('Tautan berita berhasil disalin ke clipboard.', 'success');
            } catch (err) {
                notify(shareUrl, 'info');
            }
            return;
        }

        // Edit button
        const editBtn = event.target.closest('[data-edit-news]');
        if (editBtn) {
            resetForm();
            const id = editBtn.dataset.editNews;
            const title = editBtn.dataset.title;
            const imageUrl = editBtn.dataset.image;
            const card = editBtn.closest('article');
            const content = card ? card.querySelector('.news-content').textContent : '';

            document.getElementById('newsId').value = id;
            document.getElementById('newsTitle').value = title;
            document.getElementById('newsContent').value = content;
            document.getElementById('newsModalTitle').textContent = 'Edit Berita';
            document.getElementById('submitNews').innerHTML = 'Simpan Perubahan';

            if (imageUrl) {
                if (imagePreviewImg) imagePreviewImg.src = imageUrl;
                if (imagePreviewName) imagePreviewName.textContent = 'Gambar Terlampir';
                if (imagePreviewSize) imagePreviewSize.textContent = 'Tersimpan di Cloud';
                if (imagePreviewContainer) {
                    imagePreviewContainer.classList.remove('d-none');
                    imagePreviewContainer.classList.add('d-flex');
                }
            }

            modal.show();
            return;
        }

        // Delete button
        const removeBtn = event.target.closest('[data-delete-news]');
        if (removeBtn) {
            const confirmed = window.appConfirm ? await window.appConfirm('Apakah Anda yakin ingin menghapus berita ini?') : confirm('Apakah Anda yakin ingin menghapus berita ini?');
            if (!confirmed) return;

            try {
                const response = await fetch(`${BASE_URL}app/api/news.php?action=delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: removeBtn.dataset.deleteNews })
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Gagal menghapus berita.');
                notify('Berita berhasil dihapus.', 'success');
                await loadNews();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('submitNews');
        const originalText = button.innerHTML;

        const id = document.getElementById('newsId').value;
        const title = document.getElementById('newsTitle').value.trim();
        const content = document.getElementById('newsContent').value.trim();

        if (!title || !content) {
            notify('Judul dan isi berita wajib diisi.', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('action', id ? 'update' : 'create');
        if (id) formData.append('id', id);
        formData.append('title', title);
        formData.append('content', content);

        if (removeImageFlag) {
            formData.append('remove_image', removeImageFlag.value);
        }

        if (imageInput && imageInput.files && imageInput.files[0]) {
            formData.append('image', imageInput.files[0]);
        }

        try {
            let data;
            if (typeof window.uploadWithProgress === 'function') {
                data = await window.uploadWithProgress(`${BASE_URL}app/api/news.php`, formData, button);
            } else {
                button.disabled = true;
                button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...`;
                const response = await fetch(`${BASE_URL}app/api/news.php`, {
                    method: 'POST',
                    body: formData
                });
                data = await response.json();
            }

            if (!data || !data.success) throw new Error(data?.message || 'Gagal menyimpan berita.');

            modal.hide();
            resetForm();
            notify(id ? 'Berita berhasil diperbarui.' : 'Berita berhasil diterbitkan.', 'success');
            await loadNews();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    });

    loadNews();
});
