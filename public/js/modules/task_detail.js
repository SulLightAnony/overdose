/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Task Detail
 * FILE LOCATION: public/js/modules/task_detail.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini mengontrol seluruh logika interaksi dinamik dan komunikasi AJAX
 * pada Halaman Detail Tugas (app/views/task_detail.php). Memproses pemuatan detail tugas,
 * penanganan status Soft-Delete, render multi-file lampiran (Buka & Download), toggle
 * status penyelesaian tugas, render daftar mahasiswa selesai (dengan opsi privasi),
 * pengelolaan diskusi/komentar publik, serta aksi Edit dan Soft-Delete Tugas.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Views Consumer: app/views/task_detail.php
 * - API Endpoints:
 *   * GET app/api/tasks.php?action=detail (Ambil Detail, File, Completions, & Komentar)
 *   * POST app/api/tasks.php?action=toggle_completion (Selesai / Batal Selesai)
 *   * POST app/api/tasks.php?action=add_comment (Tambah Komentar Publik)
 *   * POST / PUT app/api/tasks.php (Update Tugas & Tambah File Baru)
 *   * POST / DELETE app/api/tasks.php (Soft-Delete Row DB & Hard-Delete File Server)
 * 
 * LOGIKA & ALUR KERJA (HOW IT WORKS):
 * 1. Inisialisasi & Fetch Detail Tugas:
 *    - Membaca `CURRENT_TASK_ID` global dari view.
 *    - Melakukan fetch GET ke `app/api/tasks.php?action=detail&taskId=CURRENT_TASK_ID`.
 * 
 * 2. Penanganan Proteksi Soft-Delete Item (`deletionStatus === 1`):
 *    - Jika tugas berstatus dihapus, sembunyikan `#main-content-wrapper`.
 *    - Render banner peringatan khusus pada `#alert-container`:
 *      "Tugas ini telah dihapus oleh [userName] pada [timestamp]."
 *    - Mencegah akses ke deskripsi, file lampiran, dan aksi pengeditan/komentar.
 * 
 * 3. Render Detail & Multi-File Lampiran:
 *    - Render judul, matkul, tipe tugas, deadline, dan badge status (Tersedia / Selesai / Telat).
 *    - Render metadata pembuat & editor terakhir (lengkap dengan role level badge).
 *    - Render daftar file lampiran: Setiap file memiliki tombol "Buka" (membuka tab baru via `target="_blank"`)
 *      dan tombol "Download" (mengunduh langsung file via atribut `download`).
 * 
 * 4. Interaksi Toggle "Tandai Selesai":
 *    - Memantau event click pada `#btnToggleComplete`.
 *    - Mengirim POST AJAX ke `tasks.php?action=toggle_completion`.
 *    - Memperbarui teks/warna tombol dan memperbarui daftar mahasiswa selesai secara real-time.
 * 
 * 5. Render Daftar Mahasiswa Selesai & Proteksi Anonim:
 *    - Merender list pengguna yang telah menyelesaikan tugas.
 *    - Jika `hideCompletedIdentity === 1`, ganti avatar dengan default dan tampilkan nama "Mahasiswa Rahasia".
 * 
 * 6. Diskusi / Komentar Publik:
 *    - Merender thread komentar dari `task_comments`.
 *    - Submit `#commentForm` mengirim teks komentar via AJAX POST `tasks.php?action=add_comment`.
 *    - Sisipkan komentar baru ke DOM tanpa reload halaman secara utuh.
 * 
 * 7. Form Handler Edit Tugas & Soft-Delete Task:
 *    - Populate data tugas lama ke dalam modal `#editTaskModal`.
 *    - Form `#formEditTask` mengirim data pembaruan via multipart/form-data (`_method=PUT`).
 *    - Tombol `#btnDeleteTask` memicu konfirmasi `confirm()`. Jika disetujui, kirim request DELETE ke `tasks.php`.
 *    - Proses delete: Baris DB di-soft-delete (`deletionStatus = 1`), file fisik di-hard-delete (`unlink()`), 
 *      poin kontribusiauthor TETAP BERTAHAN (+1).
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Elemen DOM Container
    const skeletonLoader = document.getElementById('skeleton-loader');
    const mainContentWrapper = document.getElementById('main-content-wrapper');
    const alertContainer = document.getElementById('alert-container');
    const backToCourseBtn = document.getElementById('backToCourseBtn');

    // Elemen Data Tugas
    const courseCodeBadge = document.getElementById('courseCodeBadge');
    const taskTypeBadge = document.getElementById('taskTypeBadge');
    const taskStatusBadgeContainer = document.getElementById('taskStatusBadgeContainer');
    const taskTitle = document.getElementById('taskTitle');
    const courseNameTitle = document.getElementById('courseNameTitle');
    const taskDeadlineText = document.getElementById('taskDeadlineText');
    
    // Elemen Author & Editor
    const authorAvatar = document.getElementById('authorAvatar');
    const authorName = document.getElementById('authorName');
    const authorRoleDate = document.getElementById('authorRoleDate');
    const editorContainer = document.getElementById('editorContainer');
    const editorInfo = document.getElementById('editorInfo');
    
    // Elemen Deskripsi & File
    const taskDescription = document.getElementById('taskDescription');
    const taskFilesContainer = document.getElementById('taskFilesContainer');
    
    // Elemen Aksi
    const btnToggleComplete = document.getElementById('btnToggleComplete');
    const completeBtnText = document.getElementById('completeBtnText');
    const btnEditTask = document.getElementById('btnEditTask');
    const btnDeleteTask = document.getElementById('btnDeleteTask');

    // Elemen Completions & Comments
    const completionCountBadge = document.getElementById('completionCountBadge');
    const completionsList = document.getElementById('completionsList');
    const commentsContainer = document.getElementById('commentsContainer');
    const commentForm = document.getElementById('commentForm');
    const commentContent = document.getElementById('commentContent');
    const btnSubmitComment = document.getElementById('btnSubmitComment');

    // Elemen Form Edit
    const formEditTask = document.getElementById('formEditTask');

    // State Global
    let isTaskCompletedByUser = false;

    // 1. Inisialisasi & Fetch Detail Tugas
    function loadTaskDetail() {
        fetch(`${BASE_URL}app/api/tasks.php?action=detail&taskId=${CURRENT_TASK_ID}`)
            .then(response => response.json())
            .then(res => {
                skeletonLoader.classList.add('d-none');

                if (!res.success || !res.data) {
                    alertContainer.innerHTML = `<div class="alert alert-danger fw-bold">Gagal memuat detail tugas: ${escapeHtml(res.message || 'Data tidak ditemukan.')}</div>`;
                    return;
                }

                const task = res.data.task;
                const files = res.data.files;
                const completions = res.data.completions;
                const comments = res.data.comments;
                const canEdit = res.data.canEdit;
                isTaskCompletedByUser = res.data.isCompleted;

                // Set Tombol Back ke Course
                backToCourseBtn.href = `${BASE_URL}app/views/course_detail.php?id=${task.courseId}`;

                // 2. Penanganan Proteksi Soft-Delete Item
                if (parseInt(task.deletionStatus) === 1) {
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger border-danger shadow-sm">
                            <h5 class="alert-heading fw-bold">Akses Diblokir</h5>
                            <p class="mb-0">Tugas ini telah dihapus oleh <strong>${escapeHtml(task.deletedByUserName || 'Sistem')}</strong> pada <strong>${formatDate(task.deletedAt)}</strong>.</p>
                        </div>
                    `;
                    return; // Hentikan eksekusi, main content tetap d-none
                }

                // Tampilkan Konten Utama jika tidak dihapus
                mainContentWrapper.classList.remove('d-none');

                // 3. Render Detail Header
                courseCodeBadge.textContent = task.courseCode || 'Matkul';
                taskTypeBadge.textContent = task.taskType || 'Tugas';
                taskTitle.textContent = task.taskTitle;
                courseNameTitle.textContent = task.courseTitle;
                
                // Cek Status Deadline
                const now = new Date();
                const dueDate = new Date(task.dueDate);
                let badgeColor = 'primary';
                let statusText = 'Tersedia';
                
                if (isTaskCompletedByUser) {
                    badgeColor = 'success';
                    statusText = 'Selesai';
                } else if (dueDate < now) {
                    badgeColor = 'danger';
                    statusText = 'Telat';
                }

                taskStatusBadgeContainer.innerHTML = `<span class="badge bg-${badgeColor} fs-6">${statusText}</span>`;
                taskDeadlineText.innerHTML = `Tenggat: ${formatDate(task.dueDate)}`;

                // Render Pembuat Tugas
                authorAvatar.src = task.authorAvatar ? `${BASE_URL}${task.authorAvatar}` : `${BASE_URL}public/assets/img/logo.png`;
                authorAvatar.onerror = function() { this.src = `${BASE_URL}public/assets/img/logo.png`; };
                authorName.textContent = task.authorName;
                authorRoleDate.innerHTML = `<span class="badge bg-secondary me-1">${task.authorRole}</span> Dibuat: ${formatDate(task.createdAt)}`;

                // Render Editor Terakhir (Jika ada)
                if (task.lastEditedByUserId) {
                    editorContainer.classList.remove('d-none');
                    editorInfo.textContent = `Terakhir diubah oleh ${task.editorName} pada ${formatDate(task.updatedAt)}`;
                }

                // Render Deskripsi
                taskDescription.textContent = task.taskDescription || 'Tidak ada deskripsi.';

                // Render File Lampiran
                if (files && files.length > 0) {
                    let filesHtml = '';
                    files.forEach(f => {
                        filesHtml += `
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-3">
                                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                    <span>${escapeHtml(f.fileName)}</span>
                                    <small class="text-muted ms-2 d-none d-md-inline">(${(f.fileSize / 1024).toFixed(2)} KB)</small>
                                </div>
                                <div class="btn-group btn-group-sm flex-shrink-0">
                                    <a href="${BASE_URL}${f.filePath}" target="_blank" class="btn btn-outline-primary">Buka</a>
                                    <a href="${BASE_URL}${f.filePath}" download="${escapeHtml(f.fileName)}" class="btn btn-primary">Download</a>
                                </div>
                            </div>
                        `;
                    });
                    taskFilesContainer.innerHTML = filesHtml;
                } else {
                    taskFilesContainer.innerHTML = `<div class="text-muted fst-italic p-2">Tidak ada lampiran file.</div>`;
                }

                // Pengaturan Tombol Edit & Hapus (Otorisasi)
                if (!canEdit) {
                    btnEditTask.classList.add('d-none');
                    btnDeleteTask.classList.add('d-none');
                } else {
                    btnEditTask.classList.remove('d-none');
                    btnDeleteTask.classList.remove('d-none');
                    
                    // Populate Form Edit
                    document.getElementById('editCourseId').value = task.courseId;
                    document.getElementById('editTaskTitle').value = task.taskTitle;
                    document.getElementById('editTaskType').value = task.taskType;
                    // Format datetime-local: YYYY-MM-DDThh:mm
                    if (task.dueDate) {
                        const localDate = new Date(task.dueDate);
                        localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
                        document.getElementById('editDueDate').value = localDate.toISOString().slice(0, 16);
                    }
                    document.getElementById('editTaskDescription').value = task.taskDescription;
                }

                // Render UI Toggle Selesai
                updateCompletionBtnUI();

                // Render Daftar Selesai & Komentar
                renderCompletions(completions);
                renderComments(comments);
            })
            .catch(err => {
                skeletonLoader.classList.add('d-none');
                console.error('Error fetching task details:', err);
                alertContainer.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan koneksi saat memuat detail tugas.</div>`;
            });
    }

    // 4. Update UI Tombol Toggle Selesai
    function updateCompletionBtnUI() {
        if (isTaskCompletedByUser) {
            btnToggleComplete.classList.remove('btn-success');
            btnToggleComplete.classList.add('btn-outline-danger');
            completeBtnText.textContent = 'Batal Selesai';
        } else {
            btnToggleComplete.classList.remove('btn-outline-danger');
            btnToggleComplete.classList.add('btn-success');
            completeBtnText.textContent = 'Tandai Selesai';
        }
    }

    btnToggleComplete.addEventListener('click', function() {
        btnToggleComplete.disabled = true;
        
        let formData = new FormData();
        formData.append('action', 'toggle_completion');
        formData.append('taskId', CURRENT_TASK_ID);

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Reload hanya bagian completions untuk mendapatkan data terbaru
                loadTaskDetail(); 
            } else {
                alert('Gagal merubah status: ' + res.message);
                btnToggleComplete.disabled = false;
            }
        })
        .catch(err => {
            console.error('Toggle Error:', err);
            alert('Terjadi kesalahan koneksi.');
            btnToggleComplete.disabled = false;
        });
    });

    // 5. Render Daftar Mahasiswa Selesai (Proteksi Anonim)
    function renderCompletions(completions) {
        completionCountBadge.textContent = completions ? completions.length : 0;
        
        if (!completions || completions.length === 0) {
            completionsList.innerHTML = `<li class="list-group-item text-muted text-center py-4 fst-italic">Belum ada yang menyelesaikan tugas ini.</li>`;
            return;
        }

        let html = '';
        completions.forEach(c => {
            const isAnonymous = parseInt(c.hideCompletedIdentity) === 1;
            const displayName = isAnonymous ? 'Mahasiswa Rahasia' : escapeHtml(c.userName);
            const displayRole = isAnonymous ? 'Anonim' : escapeHtml(c.roleLevel);
            const displayAvatar = isAnonymous || !c.avatarUrl ? `${BASE_URL}public/assets/img/logo.png` : `${BASE_URL}${c.avatarUrl}`;

            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                    <div class="d-flex align-items-center">
                        <img src="${displayAvatar}" class="rounded-circle border me-3" style="width: 35px; height: 35px; object-fit: cover;" alt="Avatar">
                        <div>
                            <div class="fw-bold fs-6" style="line-height: 1.2;">${displayName}</div>
                            <small class="text-muted" style="font-size: 0.75rem;"><span class="badge bg-light text-dark border">${displayRole}</span></small>
                        </div>
                    </div>
                    <div class="text-muted text-end" style="font-size: 0.75rem;">
                        Selesai:<br>${formatDate(c.completedAt)}
                    </div>
                </li>
            `;
        });
        completionsList.innerHTML = html;
    }

    // 6. Diskusi / Komentar Publik
    function renderComments(comments) {
        if (!comments || comments.length === 0) {
            commentsContainer.innerHTML = `<div class="text-muted text-center py-4 fst-italic">Belum ada diskusi. Jadilah yang pertama berkomentar!</div>`;
            return;
        }

        let html = '';
        comments.forEach(c => {
            const avatar = c.avatarUrl ? `${BASE_URL}${c.avatarUrl}` : `${BASE_URL}public/assets/img/logo.png`;
            html += `
                <div class="d-flex p-3 bg-light rounded border-start border-4 border-secondary">
                    <img src="${avatar}" class="rounded-circle me-3 border" style="width: 40px; height: 40px; object-fit: cover;" alt="Avatar">
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">${escapeHtml(c.userName)} <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">${escapeHtml(c.roleLevel)}</span></span>
                            <small class="text-muted" style="font-size: 0.75rem;">${formatDate(c.createdAt)}</small>
                        </div>
                        <div class="text-break" style="font-size: 0.9rem; white-space: pre-wrap;">${escapeHtml(c.commentContent)}</div>
                    </div>
                </div>
            `;
        });
        commentsContainer.innerHTML = html;
    }

    commentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const content = commentContent.value.trim();
        if (content === '') return;

        btnSubmitComment.disabled = true;
        btnSubmitComment.textContent = 'Mengirim...';

        let formData = new FormData();
        formData.append('action', 'add_comment');
        formData.append('taskId', CURRENT_TASK_ID);
        formData.append('commentContent', content);

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                commentContent.value = '';
                loadTaskDetail(); // Reload comments
            } else {
                alert('Gagal mengirim komentar: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Comment Error:', err);
            alert('Terjadi kesalahan saat mengirim komentar.');
        })
        .finally(() => {
            btnSubmitComment.disabled = false;
            btnSubmitComment.textContent = 'Kirim Komentar';
        });
    });

    // 7. Form Handler Edit Tugas (PUT via POST)
    formEditTask.addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btnSubmitEditTask');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Menyimpan...';

        let formData = new FormData(this);

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST', // POST digunakan karena Form Data mengirim '_method=PUT'
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert('Tugas berhasil diperbarui!');
                bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                document.getElementById('editTaskAttachments').value = '';
                loadTaskDetail();
            } else {
                alert('Gagal: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Edit Error:', err);
            alert('Terjadi kesalahan server saat memperbarui tugas.');
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan Perubahan';
        });
    });

    // 8. Soft-Delete Tugas (DELETE via fetch)
    btnDeleteTask.addEventListener('click', function() {
        if (!confirm('Peringatan: Apakah Anda yakin ingin menghapus tugas ini? Aksi ini akan menghapus tugas dari daftar utama (Soft-Delete) dan menghapus lampiran file fisik secara permanen.')) {
            return;
        }

        btnDeleteTask.disabled = true;
        btnDeleteTask.textContent = 'Menghapus...';

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ taskId: CURRENT_TASK_ID })
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert('Tugas berhasil dihapus.');
                // Redirect kembali ke halaman course karena tugas ini sudah tidak bisa diakses
                window.location.href = backToCourseBtn.href;
            } else {
                alert('Gagal menghapus: ' + res.message);
                btnDeleteTask.disabled = false;
                btnDeleteTask.textContent = 'Hapus';
            }
        })
        .catch(err => {
            console.error('Delete Error:', err);
            alert('Terjadi kesalahan saat menghapus tugas.');
            btnDeleteTask.disabled = false;
            btnDeleteTask.textContent = 'Hapus';
        });
    });

    // Utilitas Formatter
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString('id-ID', options);
    }

    // Mulai eksekusi
    loadTaskDetail();
});