document.addEventListener('DOMContentLoaded', function () {
    const skeletonLoader = document.getElementById('skeleton-loader');
    const mainContentWrapper = document.getElementById('main-content-wrapper');
    const alertContainer = document.getElementById('alert-container');
    const backToCourseBtn = document.getElementById('backToCourseBtn');
    const materialTitle = document.getElementById('materialTitle');
    const courseNameTitle = document.getElementById('courseNameTitle');
    const bannerAuthorInfo = document.getElementById('bannerAuthorInfo');
    const materialDescription = document.getElementById('materialDescription');
    const materialFilesGrid = document.getElementById('materialFilesGrid');
    const noFilesText = document.getElementById('noFilesText');
    const btnDropdownEditMaterial = document.getElementById('btnDropdownEditMaterial');
    const btnDropdownDeleteMaterial = document.getElementById('btnDropdownDeleteMaterial');
    const formEditMaterial = document.getElementById('formEditMaterial');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatIndoDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString.replace(/-/g, '/'));
        if (Number.isNaN(date.getTime())) return dateString;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const d = date.getDate();
        const m = months[date.getMonth()];
        const y = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, '0');
        const mm = String(date.getMinutes()).padStart(2, '0');
        return `${d} ${m} ${y}, ${hh}.${mm}`;
    }

    function formatFileSize(bytes) {
        const size = Number(bytes || 0);
        if (size <= 0) return '0 KB';
        if (size < 1024 * 1024) {
            return `${(size / 1024).toFixed(1)} KB`;
        }
        return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    }

    function getFileIconMeta(fileName) {
        const ext = (fileName || '').split('.').pop().toLowerCase();
        switch (ext) {
            case 'pdf':
                return { icon: 'bi-file-earmark-pdf-fill', color: '#dc3545', label: 'PDF' };
            case 'doc':
            case 'docx':
                return { icon: 'bi-file-earmark-word-fill', color: '#0d6efd', label: 'Word' };
            case 'xls':
            case 'xlsx':
                return { icon: 'bi-file-earmark-excel-fill', color: '#198754', label: 'Excel' };
            case 'ppt':
            case 'pptx':
                return { icon: 'bi-file-earmark-ppt-fill', color: '#fd7e14', label: 'PowerPoint' };
            case 'zip':
            case 'rar':
            case '7z':
            case 'tar':
            case 'gz':
                return { icon: 'bi-file-earmark-zip-fill', color: '#ffc107', label: 'Arsip' };
            default:
                return { icon: 'bi-file-earmark-text-fill', color: '#6c757d', label: 'Dokumen' };
        }
    }

    function renderFilesGrid(files) {
        const materialFilesDivider = document.getElementById('materialFilesDivider');
        if (!files || files.length === 0) {
            materialFilesGrid.innerHTML = '';
            if (noFilesText) noFilesText.classList.add('d-none');
            if (materialFilesDivider) materialFilesDivider.classList.add('d-none');
            return;
        }

        if (noFilesText) noFilesText.classList.add('d-none');
        if (materialFilesDivider) materialFilesDivider.classList.remove('d-none');

        materialFilesGrid.innerHTML = files.map(file => {
            const fileUrl = /^https?:\/\//i.test(file.filePath) ? file.filePath : `${BASE_URL}${encodeURI(file.filePath)}`;
            let downloadUrl = fileUrl;
            const driveMatch = file.filePath ? file.filePath.match(/\/d\/([a-zA-Z0-9_-]+)/) : null;
            if (driveMatch && driveMatch[1]) {
                downloadUrl = `https://drive.google.com/uc?export=download&id=${driveMatch[1]}`;
            }

            const meta = getFileIconMeta(file.fileName);

            return `
                <div class="col">
                    <div class="card border-0 shadow-none bg-transparent rounded-3 p-2 text-center h-100 position-relative file-grid-card">
                        <a href="${downloadUrl}" download="${escapeHtml(file.fileName)}" class="btn btn-sm btn-light border-0 rounded-circle position-absolute top-0 end-0 m-1 p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" title="Unduh File" onclick="event.stopPropagation();">
                            <i class="bi bi-download text-secondary" style="font-size: 1.0rem;"></i>
                        </a>

                        <a href="${fileUrl}" target="_blank" rel="noopener noreferrer" class="text-decoration-none d-block py-1">
                            <div class="my-2">
                                <i class="bi ${meta.icon}" style="font-size: 2.0rem; color: ${meta.color};"></i>
                            </div>
                            <div class="fw-semibold text-dark text-truncate small mt-1 px-1" title="${escapeHtml(file.fileName)}" style="font-size: 0.8rem;">
                                ${escapeHtml(file.fileName)}
                            </div>
                            <small class="text-muted d-block mt-0.5" style="font-size: 0.7rem;">
                                ${formatFileSize(file.fileSize)}
                            </small>
                        </a>
                    </div>
                </div>
            `;
        }).join('');
    }

    function linkifyDescription(value) {
        const pattern = /https?:\/\/[^\s<]+/gi;
        let html = '';
        let lastIndex = 0;
        for (const match of value.matchAll(pattern)) {
            html += escapeHtml(value.slice(lastIndex, match.index));
            const url = match[0];
            const safeUrl = escapeHtml(url);
            html += `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${safeUrl}</a>`;
            lastIndex = match.index + url.length;
        }
        return html + escapeHtml(value.slice(lastIndex));
    }

    function loadMaterialDetail() {
        fetch(`${BASE_URL}app/api/materials.php?action=detail&materialId=${encodeURIComponent(CURRENT_MATERIAL_ID)}`)
            .then(response => response.json())
            .then(result => {
                skeletonLoader.classList.add('d-none');
                if (!result.success || !result.data || !result.data.material) {
                    alertContainer.innerHTML = `<div class="alert alert-danger rounded-4">${escapeHtml(result.message || 'Materi tidak ditemukan.')}</div>`;
                    return;
                }

                const material = result.data.material;
                if (Number(material.deletionStatus) === 1) {
                    alertContainer.innerHTML = `<div class="alert alert-danger rounded-4"><strong>Materi ini telah dihapus.</strong><br>${escapeHtml(result.data.deletedMessage || 'Materi tidak lagi tersedia.')}</div>`;
                    return;
                }

                mainContentWrapper.classList.remove('d-none');
                materialTitle.textContent = material.materialTitle || '';
                courseNameTitle.textContent = material.courseTitle ? `${material.courseCode || ''} - ${material.courseTitle}` : (material.courseCode || '');
                materialDescription.innerHTML = linkifyDescription(material.materialDescription || 'Tidak ada deskripsi.');

                // Info pembuat di bagian bawah banner
                bannerAuthorInfo.innerHTML = `Ditambahkan oleh ${escapeHtml(material.authorName || 'Pengguna')} pada ${formatIndoDate(material.createdAt)}`;

                backToCourseBtn.href = `${BASE_URL}course-detail?id=${encodeURIComponent(material.courseId)}&tab=materials`;
                backToCourseBtn.onclick = function() {
                    sessionStorage.setItem(`course_restore_scroll_${material.courseId}`, '1');
                };

                renderFilesGrid(result.data.files || []);

                if (result.data.canEdit) {
                    if (btnDropdownEditMaterial) btnDropdownEditMaterial.classList.remove('d-none');
                    if (btnDropdownDeleteMaterial) btnDropdownDeleteMaterial.classList.remove('d-none');

                    document.getElementById('editMaterialTitle').value = material.materialTitle || '';
                    document.getElementById('editMaterialDescription').value = material.materialDescription || '';
                }
            })
            .catch(error => {
                skeletonLoader.classList.add('d-none');
                console.error('Error fetching material detail:', error);
                alertContainer.innerHTML = '<div class="alert alert-danger rounded-4">Terjadi kesalahan koneksi.</div>';
            });
    }

    formEditMaterial.addEventListener('submit', function (event) {
        event.preventDefault();
        const submitButton = document.getElementById('btnSubmitEditMaterial');
        const formData = new FormData(formEditMaterial);

        window.uploadWithProgress(`${BASE_URL}app/api/materials.php`, formData, submitButton)
            .then(result => {
                if (!result.success) throw new Error(result.message || 'Materi gagal diperbarui.');
                bootstrap.Modal.getInstance(document.getElementById('editMaterialModal'))?.hide();
                window.clearMultiFileInputs?.(formEditMaterial);
                loadMaterialDetail();
            })
            .catch(error => window.appToast?.(error.message, 'danger'))
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = 'Simpan Perubahan';
            });
    });

    if (btnDropdownDeleteMaterial) {
        btnDropdownDeleteMaterial.addEventListener('click', async function () {
            if (!await window.appConfirm('Hapus materi ini? File lampiran akan dihapus permanen.')) return;
            btnDropdownDeleteMaterial.disabled = true;

            fetch(`${BASE_URL}app/api/materials.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ materialId: CURRENT_MATERIAL_ID })
            })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) throw new Error(result.message || 'Materi gagal dihapus.');
                    window.location.href = backToCourseBtn.href;
                })
                .catch(error => {
                    window.appToast?.(error.message, 'danger');
                    btnDropdownDeleteMaterial.disabled = false;
                });
        });
    }

    loadMaterialDetail();
});
