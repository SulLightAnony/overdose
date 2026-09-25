document.addEventListener('DOMContentLoaded', function () {
    const skeletonLoader = document.getElementById('skeleton-loader');
    const mainContentWrapper = document.getElementById('main-content-wrapper');
    const alertContainer = document.getElementById('alert-container');
    const backToCourseBtn = document.getElementById('backToCourseBtn');
    const courseCodeBadge = document.getElementById('courseCodeBadge');
    const materialDateText = document.getElementById('materialDateText');
    const materialTitle = document.getElementById('materialTitle');
    const courseNameTitle = document.getElementById('courseNameTitle');
    const authorAvatar = document.getElementById('authorAvatar');
    const authorName = document.getElementById('authorName');
    const authorRole = document.getElementById('authorRole');
    const editorContainer = document.getElementById('editorContainer');
    const editorInfo = document.getElementById('editorInfo');
    const materialDescription = document.getElementById('materialDescription');
    const materialFilesContainer = document.getElementById('materialFilesContainer');
    const actionButtonsContainer = document.getElementById('actionButtonsContainer');
    const formEditMaterial = document.getElementById('formEditMaterial');
    const btnEditMaterial = document.getElementById('btnEditMaterial');
    const btnDeleteMaterial = document.getElementById('btnDeleteMaterial');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('id-ID', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    }

    function setFallbackAvatar(image) {
        image.src = `${BASE_URL}public/assets/img/logo.png`;
    }

    function renderFiles(files) {
        if (!files || files.length === 0) {
            materialFilesContainer.innerHTML = '<div class="text-muted fst-italic p-2">Tidak ada lampiran file.</div>';
            return;
        }

        materialFilesContainer.innerHTML = files.map(file => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="text-truncate me-3">
                    <i class="bi bi-file-earmark-text me-2 text-success"></i>
                    <span>${escapeHtml(file.fileName)}</span>
                    <small class="text-muted ms-2 d-none d-md-inline">(${(Number(file.fileSize || 0) / 1024).toFixed(2)} KB)</small>
                </div>
                <div class="btn-group btn-group-sm flex-shrink-0">
                    <a href="${BASE_URL}${encodeURI(file.filePath)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success">Buka</a>
                    <a href="${BASE_URL}${encodeURI(file.filePath)}" download="${escapeHtml(file.fileName)}" class="btn btn-success">Download</a>
                </div>
            </div>
        `).join('');
    }

    function loadMaterialDetail() {
        fetch(`${BASE_URL}app/api/materials.php?action=detail&materialId=${encodeURIComponent(CURRENT_MATERIAL_ID)}`)
            .then(response => response.json())
            .then(result => {
                skeletonLoader.classList.add('d-none');
                if (!result.success || !result.data || !result.data.material) {
                    alertContainer.innerHTML = `<div class="alert alert-danger">${escapeHtml(result.message || 'Materi tidak ditemukan.')}</div>`;
                    return;
                }

                const material = result.data.material;
                if (Number(material.deletionStatus) === 1) {
                    alertContainer.innerHTML = `<div class="alert alert-danger"><strong>Materi ini telah dihapus.</strong><br>${escapeHtml(result.data.deletedMessage || 'Materi tidak lagi tersedia.')}</div>`;
                    return;
                }

                mainContentWrapper.classList.remove('d-none');
                courseCodeBadge.textContent = material.courseCode || 'Matkul';
                materialTitle.textContent = material.materialTitle || '';
                courseNameTitle.textContent = material.courseTitle || '';
                materialDateText.textContent = `Diupload: ${formatDate(material.createdAt)}`;
                materialDescription.textContent = material.materialDescription || 'Tidak ada deskripsi.';
                authorName.textContent = material.authorName || 'Pengguna';
                authorRole.textContent = `${material.authorRole || 'Keroco'} | Diupload: ${formatDate(material.createdAt)}`;
                authorAvatar.src = material.authorAvatar ? `${BASE_URL}${material.authorAvatar}` : `${BASE_URL}public/assets/img/logo.png`;
                authorAvatar.onerror = () => setFallbackAvatar(authorAvatar);
                backToCourseBtn.href = `${BASE_URL}app/views/course_detail.php?id=${encodeURIComponent(material.courseId)}`;
                renderFiles(result.data.files || []);

                if (material.lastEditedByUserId) {
                    editorContainer.classList.remove('d-none');
                    editorInfo.textContent = `Terakhir diubah oleh ${material.editorName || 'Pengguna'} pada ${formatDate(material.updatedAt)}`;
                }

                if (result.data.canEdit) {
                    actionButtonsContainer.classList.remove('d-none');
                    document.getElementById('editMaterialTitle').value = material.materialTitle || '';
                    document.getElementById('editMaterialDescription').value = material.materialDescription || '';
                }
            })
            .catch(error => {
                skeletonLoader.classList.add('d-none');
                console.error('Error fetching material detail:', error);
                alertContainer.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan koneksi.</div>';
            });
    }

    formEditMaterial.addEventListener('submit', function (event) {
        event.preventDefault();
        const submitButton = document.getElementById('btnSubmitEditMaterial');
        submitButton.disabled = true;
        const formData = new FormData(formEditMaterial);

        fetch(`${BASE_URL}app/api/materials.php`, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (!result.success) throw new Error(result.message || 'Materi gagal diperbarui.');
                bootstrap.Modal.getInstance(document.getElementById('editMaterialModal'))?.hide();
                loadMaterialDetail();
            })
            .catch(error => alert(error.message))
            .finally(() => { submitButton.disabled = false; });
    });

    btnDeleteMaterial.addEventListener('click', function () {
        if (!confirm('Hapus materi ini? File lampiran akan dihapus permanen.')) return;
        btnDeleteMaterial.disabled = true;

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
                alert(error.message);
                btnDeleteMaterial.disabled = false;
            });
    });

    loadMaterialDetail();
});
