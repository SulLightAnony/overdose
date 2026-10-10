/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Task Answers (Answer Sharing Center)
 * FILE LOCATION: public/js/modules/task_answers.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini mengontrol seluruh logika dinamik, komunikasi AJAX, dan manipulasi DOM
 * pada Halaman Sharing Jawaban Tugas (app/views/task_answers.php). Memproses pemuatan daftar
 * jawaban publik, pengiriman form share jawaban baru (dengan dukungan multi-file upload),
 * pembaruan postingan jawaban oleh author, serta eksekusi Full Hard-Delete postingan jawaban.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Views Consumer: app/views/task_answers.php
 * - API Endpoints:
 *   * GET app/api/task_answers.php?action=list&taskId=X (Fetch Feed Daftar Jawaban)
 *   * GET app/api/task_answers.php?action=detail&answerId=X (Fetch Detail Jawaban untuk Edit)
 *   * POST app/api/task_answers.php?action=create (Submit Share Jawaban Baru -> +1 Poin)
 *   * POST / PUT app/api/task_answers.php (_method=PUT, Update Jawaban Khusus Author)
 *   * DELETE app/api/task_answers.php (Full Hard-Delete Row DB & File Fisik Server)
 *   * GET app/api/tasks.php?action=detail&taskId=X (Mengambil Judul/Konteks Tugas untuk Banner)
 * 
 * LOGIKA & ALUR KERJA (HOW IT WORKS):
 * 1. Inisialisasi & Fetch Data:
 *    - Membaca `CURRENT_TASK_ID` dan `CURRENT_USER_ID` dari variabel global view.
 *    - Melakukan fetch paralel ke `tasks.php?action=detail` (untuk menampilkan konteks judul tugas di banner)
 *      dan `task_answers.php?action=list` (untuk mengambil seluruh postingan jawaban).
 * 
 * 2. Rendering Dynamic Feed Sharing Jawaban:
 *    - Jika data jawaban kosong, tampilkan `#emptyStateContainer` ("Belum ada jawaban yang dibagikan...").
 *    - Jika ada jawaban, render setiap kartu postingan ke `#answersListContainer`:
 *      * Header Card: Avatar mahasiswa, Nama Mahasiswa, Role Badge (Keroco/Sepuh/Primordial), Tanggal Unggah.
 *      * Konten Utama: Judul Jawaban (`answerTitle`) & Catatan Penjelas (`answerNotes`).
 *      * List Lampiran File: Setiap file terlampir memiliki tombol "Buka" (`target="_blank"`) 
 *        dan tombol "Download" (`download` attribute).
 * 
 * 3. Otorisasi Tombol Aksi (Author Only):
 *    - Tombol "Edit" dan "Hapus" HANYA dirender jika `authorId === CURRENT_USER_ID` (atau jika role user dapat override).
 *    - Pengguna lain yang bukan author tidak melihat tombol Edit/Hapus tersebut.
 * 
 * 4. Form Submit Handler (Tambah / Edit Jawaban):
 *    - Pemicu Tombol "Bagikan Jawaban Saya": Reset form `#formAnswer`, set `_method=POST`, dan ubah title modal ke "Bagikan Jawaban Baru".
 *    - Pemicu Tombol "Edit Jawaban": Ambil detail jawaban via AJAX, isi modal form, set `_method=PUT`, dan set `inputAnswerId`.
 *    - Pengiriman Form: Mengirim data via `FormData` multipart/form-data ke `app/api/task_answers.php`.
 *    - Setelah berhasil disimpan (+1 Poin Kontribusi), tampilkan notifikasi alert, tutup modal, dan reload list jawaban.
 * 
 * 5. Full Hard-Delete Handler:
 *    - Memicu konfirmasi `confirm()` saat tombol Hapus diklik oleh author.
 *    - Mengirim request DELETE JSON ke `app/api/task_answers.php`.
 *    - Menghapus permanen baris data di basis data (`DELETE FROM task_shared_answers`) 
 *      serta menghapus seluruh file fisik dari server menggunakan `unlink()`.
 *    - Poin kontribusi author TETAP BERTAHAN (tidak berkurang).
 *    - Reload feed jawaban secara otomatis.
 */

document.addEventListener('DOMContentLoaded', function() {

    // Elemen DOM
    const taskTitleContext = document.getElementById('taskTitleContext');
    const skeletonLoader = document.getElementById('skeleton-loader');
    const answersListContainer = document.getElementById('answersListContainer');
    const emptyStateContainer = document.getElementById('emptyStateContainer');
    
    // Elemen Form & Modal
    const answerModalEl = document.getElementById('answerModal');
    const answerModal = new bootstrap.Modal(answerModalEl);
    const formAnswer = document.getElementById('formAnswer');
    const btnSubmitAnswer = document.getElementById('btnSubmitAnswer');
    const formMethod = document.getElementById('formMethod');
    const inputAnswerId = document.getElementById('inputAnswerId');
    const modalTitle = document.getElementById('answerModalLabel');
    const fileRequiredStar = document.getElementById('fileRequiredStar');
    const btnShareAnswer = document.getElementById('btnShareAnswer');

    // 1. Fetch Konteks Judul Tugas (Untuk Banner Header)
    function loadTaskContext() {
        fetch(`${BASE_URL}app/api/tasks.php?action=detail&taskId=${CURRENT_TASK_ID}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data && res.data.task) {
                    const t = res.data.task;
                    taskTitleContext.innerHTML = `<span class="badge bg-info text-dark me-2">${escapeHtml(t.courseCode)}</span>${escapeHtml(t.taskTitle)}`;
                } else {
                    taskTitleContext.innerHTML = '<span class="text-danger">Konteks tugas tidak ditemukan.</span>';
                }
            })
            .catch(err => {
                console.error('Error fetching task context:', err);
                taskTitleContext.innerHTML = '<span class="text-danger">Gagal memuat konteks tugas.</span>';
            });
    }

    // 2. Fetch & Render Daftar Jawaban (Feed)
    function loadAnswers() {
        skeletonLoader.classList.remove('d-none');
        answersListContainer.classList.add('d-none');
        emptyStateContainer.classList.add('d-none');

        fetch(`${BASE_URL}app/api/task_answers.php?action=list&taskId=${CURRENT_TASK_ID}`)
            .then(response => response.json())
            .then(res => {
                skeletonLoader.classList.add('d-none');
                
                if (res.success && res.data && res.data.answers) {
                    const answers = res.data.answers;
                    
                    if (answers.length === 0) {
                        emptyStateContainer.classList.remove('d-none');
                    } else {
                        renderAnswersList(answers);
                        answersListContainer.classList.remove('d-none');
                    }
                } else {
                    window.appToast?.('Gagal memuat daftar jawaban: ' + (res.message || 'Data error.'), 'danger');
                }
            })
            .catch(err => {
                skeletonLoader.classList.add('d-none');
                console.error('Error fetching answers:', err);
                window.appToast?.('Terjadi kesalahan koneksi saat memuat daftar jawaban.', 'danger');
            });
    }

    function renderAnswersList(answers) {
        let html = '';
        
        answers.forEach(ans => {
            const isAuthor = (parseInt(ans.authorId) === CURRENT_USER_ID);
            const canDelete = Boolean(Number(ans.canDelete)) || isAuthor;
            const avatarUrl = resolveAssetUrl(ans.avatarUrl);
            
            // Render Files
            let filesHtml = '';
            if (ans.files && ans.files.length > 0) {
                filesHtml = `<div class="list-group list-group-flush mt-3 border-top pt-2">`;
                ans.files.forEach(f => {
                    const fileUrl = /^https?:\/\//i.test(f.filePath) ? f.filePath : `${BASE_URL}${f.filePath}`;
                    let downloadUrl = fileUrl;
                    const driveMatch = f.filePath ? f.filePath.match(/\/d\/([a-zA-Z0-9_-]+)/) : null;
                    if (driveMatch && driveMatch[1]) {
                        downloadUrl = `https://drive.google.com/uc?export=download&id=${driveMatch[1]}`;
                    }
                    filesHtml += `
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center bg-transparent">
                            <div class="text-truncate me-3">
                                <i class="bi bi-file-earmark-code me-2 text-info"></i>
                                <span class="fw-medium">${escapeHtml(f.fileName)}</span>
                                <small class="text-muted ms-2 d-none d-sm-inline">(${(f.fileSize / 1024).toFixed(2)} KB)</small>
                            </div>
                            <div class="btn-group btn-group-sm flex-shrink-0">
                                <a href="${fileUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info">Buka</a>
                                <a href="${downloadUrl}" target="_blank" rel="noopener noreferrer" download="${escapeHtml(f.fileName)}" class="btn btn-info text-white">Unduh</a>
                            </div>
                        </div>
                    `;
                });
                filesHtml += `</div>`;
            } else {
                filesHtml = `<div class="mt-3 border-top pt-2 text-muted fst-italic small">Tidak ada file lampiran.</div>`;
            }

            // Render Actions (Only for Author)
            let actionButtons = `
                <div class="mt-3 pt-3 border-top text-end">
                    <button type="button" class="btn btn-sm btn-outline-dark px-3 me-2" data-share-url="${BASE_URL}task-answers?taskId=${CURRENT_TASK_ID}&answerId=${ans.answerId}">Bagikan</button>
                    ${isAuthor ? '<button class="btn btn-sm btn-warning px-3 me-2 btn-edit" data-id="' + ans.answerId + '">Edit Jawaban</button>' : ''}
                    ${canDelete ? '<button class="btn btn-sm btn-danger px-3 btn-delete" data-id="' + ans.answerId + '">Hapus Permanen</button>' : ''}
                </div>`;

            html += `
                <div class="card shadow-sm mb-4 border-0 border-start border-4 border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <img src="${avatarUrl}" class="rounded-circle border me-3" style="width: 45px; height: 45px; object-fit: cover;" alt="Avatar">
                                <div>
                                    <h6 class="mb-0 fw-bold">${escapeHtml(ans.userName)}</h6>
                                    <small class="text-muted">${roleBadgeHtml(ans.roleLevel)} Dibagikan: ${formatDate(ans.createdAt)}</small>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="card-title fw-bold text-info-emphasis">${escapeHtml(ans.answerTitle)}</h5>
                        <div class="card-text text-dark" style="white-space: pre-wrap;">${escapeHtml(ans.answerNotes || '')}</div>
                        
                        ${filesHtml}
                        ${actionButtons}
                    </div>
                </div>
            `;
        });
        
        answersListContainer.innerHTML = html;
    }

    // 3. Persiapan Form (Add vs Edit Mode)
    btnShareAnswer.addEventListener('click', function() {
        formAnswer.reset();
        window.clearMultiFileInputs?.(formAnswer);
        formMethod.value = 'POST';
        inputAnswerId.value = '';
        modalTitle.textContent = 'Bagikan Jawaban Baru';
        fileRequiredStar.style.display = 'inline';
        document.getElementById('answerAttachments').required = true;
    });

    // Event Delegation untuk Tombol Edit & Delete di dalam list jawaban
    answersListContainer.addEventListener('click', function(e) {
        // Handle Delete Button
        if (e.target.classList.contains('btn-delete')) {
            const answerId = e.target.getAttribute('data-id');
            deleteAnswer(answerId);
        }
        
        // Handle Edit Button
        if (e.target.classList.contains('btn-edit')) {
            const answerId = e.target.getAttribute('data-id');
            openEditModal(answerId);
        }
    });

    // 4. Proses Hapus Permanen (Full Hard-Delete)
    function deleteAnswer(answerId) {
        return window.appConfirm('Jawaban dan seluruh lampirannya akan dihapus permanen.').then(confirmed => {
            if (!confirmed) return;

        fetch(`${BASE_URL}app/api/task_answers.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ answerId: answerId })
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                window.appToast?.('Jawaban berhasil dihapus permanen.', 'success');
                loadAnswers(); // Reload feed
            } else {
                window.appToast?.('Gagal menghapus jawaban: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Delete Error:', err);
            window.appToast?.('Terjadi kesalahan server saat mencoba menghapus jawaban.', 'danger');
        });
        });
    }

    // 5. Persiapan Edit Mode (Fetch detail data)
    function openEditModal(answerId) {
        fetch(`${BASE_URL}app/api/task_answers.php?action=detail&answerId=${answerId}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data && res.data.answer) {
                    const ans = res.data.answer;
                    
                    formAnswer.reset();
                    window.clearMultiFileInputs?.(formAnswer);
                    formMethod.value = 'PUT';
                    inputAnswerId.value = ans.answerId;
                    modalTitle.textContent = 'Edit Jawaban';
                    btnSubmitAnswer.textContent = 'Simpan Perubahan';
                    
                    document.getElementById('answerTitle').value = ans.answerTitle;
                    document.getElementById('answerNotes').value = ans.answerNotes;
                    
                    // Untuk edit, file lampiran baru bersifat opsional (tidak required)
                    fileRequiredStar.style.display = 'none';
                    document.getElementById('answerAttachments').required = false;
                    
                    answerModal.show();
                } else {
                    window.appToast?.('Gagal mengambil data jawaban untuk diedit.', 'danger');
                }
            })
            .catch(err => {
                console.error('Fetch Edit Data Error:', err);
                window.appToast?.('Terjadi kesalahan koneksi.', 'danger');
            });
    }

    if (btnShareAnswer) {
        btnShareAnswer.addEventListener('click', function() {
            formAnswer.reset();
            window.clearMultiFileInputs?.(formAnswer);
            formMethod.value = 'POST';
            inputAnswerId.value = '';
            modalTitle.textContent = 'Bagikan Jawaban';
            btnSubmitAnswer.textContent = 'Simpan & Bagikan';
            fileRequiredStar.style.display = 'inline';
            document.getElementById('answerAttachments').required = true;
        });
    }

    // 6. Submit Form Pengiriman (Create / Update via Multipart)
    formAnswer.addEventListener('submit', function(e) {
        e.preventDefault();

        let formData = new FormData(this);
        
        // Tentukan nilai parameter action untuk API create
        if (formMethod.value === 'POST') {
            formData.append('action', 'create');
        }

        window.uploadWithProgress(`${BASE_URL}app/api/task_answers.php`, formData, btnSubmitAnswer)
        .then(res => {
            if (res.success) {
                window.appToast?.(res.message, 'success');
                answerModal.hide();
                formAnswer.reset();
                window.clearMultiFileInputs?.(formAnswer);
                loadAnswers(); // Reload list
            } else {
                window.appToast?.('Gagal menyimpan: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Form Submit Error:', err);
            window.appToast?.('Terjadi kesalahan koneksi saat mengirim data.', 'danger');
        })
        .finally(() => {
            btnSubmitAnswer.disabled = false;
            btnSubmitAnswer.textContent = formMethod.value === 'PUT' ? 'Simpan Perubahan' : 'Simpan & Bagikan';
        });
    });

    // Utilitas Formatter
    function resolveAssetUrl(path) {
        if (!path) return `${BASE_URL}public/assets/img/logo.png`;
        return /^(https?:)?\/\//i.test(path) ? path : `${BASE_URL}${path}`;
    }

    function roleBadgeHtml(role) {
        if (role === 'Primordial') return '<span class="badge mt-1" style="background:linear-gradient(135deg,#be9d30,#ffd13b,#aa771c);color:#fff;font-size:.65rem;font-weight:600">Primordial</span>';
        if (role === 'Sepuh') return '<span class="badge bg-secondary text-white mt-1" style="font-size:.65rem">Sepuh</span>';
        return '<span class="text-muted d-block" style="font-size:.72rem">Keroco</span>';
    }

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

    // Eksekusi Pemuatan Awal
    loadTaskContext();
    loadAnswers();

});