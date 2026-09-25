/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Course Detail
 * FILE LOCATION: public/js/modules/course_detail.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini bertanggung jawab mengelola seluruh logika dinamik dan interaksi AJAX
 * pada halaman Detail Mata Kuliah (course_detail.php). Meliputi pemuatan header matkul,
 * perpindahan tab (Tugas vs Materi), otomatisasi penyesuaian opsi dropdown sorting, 
 * pencarian real-time (search), lazy loading data (10 item per scroll/load), otomatisasi 
 * judul materi dari file pertama, serta pengiriman form multi-file upload via AJAX.
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Views Consumer: app/views/course_detail.php
 * - API Endpoints:
 *   * app/api/courses.php?action=detail (Ambil Header Matkul)
 *   * app/api/courses.php?action=list_tasks (Ambil Daftar Tugas + Soft-Delete Items)
 *   * app/api/courses.php?action=list_materials (Ambil Daftar Materi + Soft-Delete Items)
 *   * app/api/tasks.php?action=create (Submit Form Tambah Tugas)
 *   * app/api/materials.php?action=create (Submit Form Tambah Materi)
 * 
 * LOGIKA & ALUR KERJA (HOW IT WORKS):
 * 1. Inisialisasi & Fetch Header Matkul:
 *    - Membaca `CURRENT_COURSE_ID` global dari view.
 *    - Melakukan fetch ke `app/api/courses.php?action=detail&courseId=CURRENT_COURSE_ID`.
 *    - Merender data matkul (kode, nama, dosen, kontak, jadwal, ruangan) pada `#course-header-container`.
 * 
 * 2. Pengelolaan Tab & Sorting Dinamis:
 *    - Memantau event click/shown pada tab Bootstrap (`#tab-tasks-btn` dan `#tab-materials-btn`).
 *    - Menyesuaikan opsi dropdown `#sortSelect` secara otomatis:
 *      * Pilihan Tab Tugas: `due_asc` (Deadline Terdekat), `due_desc` (Deadline Terjauh), `title_asc` (Judul A-Z), `title_desc` (Judul Z-A).
 *      * Pilihan Tab Materi: `title_asc` (Judul A-Z), `title_desc` (Judul Z-A), `date_desc` (Terbaru), `date_asc` (Terlama).
 *    - Saat tab berpindah, reset pencarian/page dan panggil fungsi fetch daftar sesuai tab aktif.
 * 
 * 3. Pencarian (Search) & Debounce:
 *    - Memantau event `input` pada `#searchInput`.
 *    - Menggunakan teknik `debounce` (delay 300ms) untuk mencegah request berlebihan saat mengetik.
 *    - Memicu reload list dari page 1 sesuai query pencarian.
 * 
 * 4. Lazy Loading & Pagination List:
 *    - Mengelola state pagination: `taskPage`, `materialPage`, `hasMoreTasks`, `hasMoreMaterials`.
 *    - Tombol "Muat Lebih Banyak" (`#btn-load-more-tasks` / `#btn-load-more-materials`) bertindak sebagai pemicu penambahan offset data.
 * 
 * 5. Rendering Kartu Tugas & Kartu Materi (Active vs Soft-Delete):
 *    - Kartu Aktif (`deletionStatus === 0`):
 *      * Tugas: Tampilkan judul, deskripsi singkat, deadline, badge status (Tersedia/Selesai/Telat). Kartu dapat diklik menuju `task_detail.php?id=taskId`.
 *      * Materi: Tampilkan judul, deskripsi singkat, author, tanggal. Kartu dapat diklik menuju `material_detail.php?id=materialId`.
 *    - Kartu Soft-Delete (`deletionStatus === 1`):
 *      * DIPAKSA ditaruh di urutan paling bawah list (sesuai ordering backend).
 *      * Tampilan visual faded out / disabled (`opacity: 0.6`, `cursor: not-allowed`).
 *      * TIDAK BISA DIKLIK (pointer-events disabled atau event click dicegah).
 *      * Menampilkan teks banner wajib: "Dihapus oleh [deletedByUserName] pada [deletedAt]".
 * 
 * 6. Fitur Otomatisasi Judul Materi (Auto-Title Feature):
 *    - Memantau event `change` pada input file `#materialAttachments`.
 *    - Mengambil file pertama dari `files[0]`.
 *    - Ekstrak nama file tanpa ekstensi (misal: "Slide_Praktikum_1.pdf" -> "Slide_Praktikum_1").
 *    - Isi otomatis ke input `#materialTitle` jika input masih kosong.
 * 
 * 7. Form Handler via AJAX Multipart:
 *    - Handler `#formTask`: Kirim `FormData` ke `app/api/tasks.php?action=create`.
 *    - Handler `#formMaterial`: Kirim `FormData` ke `app/api/materials.php?action=create`.
 *    - Tampilkan notifikasi (Toast/Alert), tutup modal, dan reload list terkait secara otomatis.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // State Aplikasi
    let currentTab = 'tasks'; // 'tasks' atau 'materials'
    let taskPage = 1;
    let materialPage = 1;
    let searchQuery = '';
    let currentSort = 'due_asc'; // Default sort untuk tugas
    let currentSemesterId = '';

    // Elemen DOM
    const headerContainer = document.getElementById('course-header-container');
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    
    const tasksListContainer = document.getElementById('tasks-list-container');
    const btnLoadMoreTasks = document.getElementById('btn-load-more-tasks');
    const tasksLoadingSpinner = document.getElementById('tasks-loading-spinner');
    
    const materialsListContainer = document.getElementById('materials-list-container');
    const btnLoadMoreMaterials = document.getElementById('btn-load-more-materials');
    const materialsLoadingSpinner = document.getElementById('materials-loading-spinner');

    const formTask = document.getElementById('formTask');
    const formMaterial = document.getElementById('formMaterial');

    // 1. Fetch Header Course
    function loadCourseHeader() {
        fetch(`${BASE_URL}app/api/courses.php?action=detail&courseId=${CURRENT_COURSE_ID}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    const c = res.data;
                    currentSemesterId = c.semesterId || '';
                    const taskSemesterInput = document.getElementById('inputTaskSemesterId');
                    const materialSemesterInput = document.getElementById('inputMaterialSemesterId');
                    if (taskSemesterInput) taskSemesterInput.value = currentSemesterId;
                    if (materialSemesterInput) materialSemesterInput.value = currentSemesterId;
                    headerContainer.innerHTML = `
                        <div class="card shadow-sm border-0 border-start border-5 border-primary">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-secondary fs-6">${escapeHtml(c.courseCode)}</span>
                                    <span class="badge bg-info text-dark fs-6">${escapeHtml(c.courseType)}</span>
                                </div>
                                <h3 class="card-title fw-bold text-primary mb-2">${escapeHtml(c.courseTitle)}</h3>
                                <div class="row mt-3 text-muted">
                                    <div class="col-md-6 mb-2">
                                        <div><strong>Dosen:</strong> ${escapeHtml(c.lecturerName || 'Belum diatur')}</div>
                                        <div><strong>Kontak:</strong> ${escapeHtml(c.lecturerEmail || '-')} | ${escapeHtml(c.lecturerPhone || '-')}</div>
                                    </div>
                                    <div class="col-md-6 mb-2 text-md-end">
                                        <div><strong>Jadwal:</strong> ${escapeHtml(c.courseDay || '-')} (${escapeHtml(c.startTime || '')} - ${escapeHtml(c.endTime || '')})</div>
                                        <div><strong>Kelas/Ruangan:</strong> ${escapeHtml(c.courseClass || '-')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    headerContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat informasi mata kuliah.</div>`;
                }
            })
            .catch(err => {
                console.error('Error fetching course detail:', err);
                headerContainer.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan koneksi.</div>`;
            });
    }

    // 2. Fetch Tasks (Lazy Loading & Soft-Delete Handle)
    function loadTasks(append = false) {
        if (!append) {
            taskPage = 1;
            tasksListContainer.innerHTML = '';
        }
        
        btnLoadMoreTasks.classList.add('d-none');
        tasksLoadingSpinner.classList.remove('d-none');

        const url = `${BASE_URL}app/api/courses.php?action=list_tasks&courseId=${CURRENT_COURSE_ID}&page=${taskPage}&limit=10&search=${encodeURIComponent(searchQuery)}&sort=${encodeURIComponent(currentSort)}`;
        
        fetch(url)
            .then(response => response.json())
            .then(res => {
                tasksLoadingSpinner.classList.add('d-none');
                if (res.success && res.data) {
                    const tasks = res.data.tasks;
                    
                    if (tasks.length === 0 && taskPage === 1) {
                        tasksListContainer.innerHTML = `<div class="col-12"><div class="alert alert-light text-center">Belum ada tugas untuk mata kuliah ini.</div></div>`;
                        return;
                    }

                    tasks.forEach(t => {
                        const isDeleted = parseInt(t.deletionStatus) === 1;
                        let cardHTML = '';

                        if (isDeleted) {
                            // Tampilan Soft-Delete (Disabled & Unclickable)
                            cardHTML = `
                                <div class="col-md-6 mb-3">
                                    <div class="card shadow-sm h-100 bg-light" style="opacity: 0.6; cursor: not-allowed;">
                                        <div class="card-body">
                                            <h5 class="card-title text-muted text-decoration-line-through">${escapeHtml(t.taskTitle)}</h5>
                                            <p class="card-text text-muted small">${escapeHtml(t.taskDescription)}</p>
                                        </div>
                                        <div class="card-footer bg-danger text-white text-center small fw-bold">
                                            Dihapus oleh ${escapeHtml(t.deletedByUserName || 'Sistem')} pada ${formatDate(t.deletedAt)}
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Tampilan Aktif
                            let badgeColor = t.statusBadge === 'Selesai' ? 'success' : (t.statusBadge === 'Telat' ? 'danger' : 'primary');
                            cardHTML = `
                                <div class="col-md-6 mb-3">
                                    <a href="${BASE_URL}app/views/task_detail.php?id=${t.taskId}" class="text-decoration-none text-dark">
                                        <div class="card shadow-sm h-100 card-hover-effect border-start border-4 border-${badgeColor}">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-${badgeColor}">${t.statusBadge}</span>
                                                    <small class="text-muted fw-bold">Tenggat: ${formatDate(t.dueDate)}</small>
                                                </div>
                                                <h5 class="card-title fw-bold text-primary">${escapeHtml(t.taskTitle)}</h5>
                                                <p class="card-text text-muted small">${escapeHtml(t.taskDescription)}</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            `;
                        }
                        tasksListContainer.insertAdjacentHTML('beforeend', cardHTML);
                    });

                    if (res.data.hasMore) {
                        btnLoadMoreTasks.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                tasksLoadingSpinner.classList.add('d-none');
                console.error('Error fetching tasks:', err);
            });
    }

    // 3. Fetch Materials (Lazy Loading & Soft-Delete Handle)
    function loadMaterials(append = false) {
        if (!append) {
            materialPage = 1;
            materialsListContainer.innerHTML = '';
        }

        btnLoadMoreMaterials.classList.add('d-none');
        materialsLoadingSpinner.classList.remove('d-none');

        const url = `${BASE_URL}app/api/courses.php?action=list_materials&courseId=${CURRENT_COURSE_ID}&page=${materialPage}&limit=10&search=${encodeURIComponent(searchQuery)}&sort=${encodeURIComponent(currentSort)}`;
        
        fetch(url)
            .then(response => response.json())
            .then(res => {
                materialsLoadingSpinner.classList.add('d-none');
                if (res.success && res.data) {
                    const materials = res.data.materials;
                    
                    if (materials.length === 0 && materialPage === 1) {
                        materialsListContainer.innerHTML = `<div class="col-12"><div class="alert alert-light text-center">Belum ada materi untuk mata kuliah ini.</div></div>`;
                        return;
                    }

                    materials.forEach(m => {
                        const isDeleted = parseInt(m.deletionStatus) === 1;
                        let cardHTML = '';

                        if (isDeleted) {
                            cardHTML = `
                                <div class="col-md-6 mb-3">
                                    <div class="card shadow-sm h-100 bg-light" style="opacity: 0.6; cursor: not-allowed;">
                                        <div class="card-body">
                                            <h5 class="card-title text-muted text-decoration-line-through">${escapeHtml(m.materialTitle)}</h5>
                                            <p class="card-text text-muted small">${escapeHtml(m.materialDescription)}</p>
                                        </div>
                                        <div class="card-footer bg-danger text-white text-center small fw-bold">
                                            Dihapus oleh ${escapeHtml(m.deletedByUserName || 'Sistem')} pada ${formatDate(m.deletedAt)}
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            cardHTML = `
                                <div class="col-md-6 mb-3">
                                    <a href="${BASE_URL}app/views/material_detail.php?id=${m.materialId}" class="text-decoration-none text-dark">
                                        <div class="card shadow-sm h-100 card-hover-effect border-start border-4 border-success">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-success">Materi</span>
                                                    <small class="text-muted fw-bold">Diupload: ${formatDate(m.createdAt)}</small>
                                                </div>
                                                <h5 class="card-title fw-bold text-success">${escapeHtml(m.materialTitle)}</h5>
                                                <p class="card-text text-muted small">${escapeHtml(m.materialDescription)}</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            `;
                        }
                        materialsListContainer.insertAdjacentHTML('beforeend', cardHTML);
                    });

                    if (res.data.hasMore) {
                        btnLoadMoreMaterials.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                materialsLoadingSpinner.classList.add('d-none');
                console.error('Error fetching materials:', err);
            });
    }

    // 4. Manajemen Tab & Opsi Sortir Dinamis
    const tabTasksBtn = document.getElementById('tab-tasks-btn');
    const tabMaterialsBtn = document.getElementById('tab-materials-btn');

    function updateSortOptions(tabName) {
        sortSelect.innerHTML = '';
        if (tabName === 'tasks') {
            sortSelect.innerHTML = `
                <option value="due_asc">Deadline Terdekat</option>
                <option value="due_desc">Deadline Terjauh</option>
                <option value="title_asc">Judul (A-Z)</option>
                <option value="title_desc">Judul (Z-A)</option>
            `;
            currentSort = 'due_asc';
        } else {
            sortSelect.innerHTML = `
                <option value="title_asc">Judul (A-Z)</option>
                <option value="title_desc">Judul (Z-A)</option>
                <option value="date_desc">Terbaru</option>
                <option value="date_asc">Terlama</option>
            `;
            currentSort = 'title_asc';
        }
    }

    tabTasksBtn.addEventListener('shown.bs.tab', function () {
        currentTab = 'tasks';
        updateSortOptions('tasks');
        loadTasks(false);
    });

    tabMaterialsBtn.addEventListener('shown.bs.tab', function () {
        currentTab = 'materials';
        updateSortOptions('materials');
        loadMaterials(false);
    });

    // 5. Fitur Search Real-time (Debounce)
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchQuery = this.value.trim();
        searchTimeout = setTimeout(() => {
            if (currentTab === 'tasks') {
                loadTasks(false);
            } else {
                loadMaterials(false);
            }
        }, 400); // 400ms delay
    });

    // 6. Fitur Sortir
    sortSelect.addEventListener('change', function() {
        currentSort = this.value;
        if (currentTab === 'tasks') {
            loadTasks(false);
        } else {
            loadMaterials(false);
        }
    });

    // 7. Tombol Load More
    btnLoadMoreTasks.addEventListener('click', function() {
        taskPage++;
        loadTasks(true);
    });

    btnLoadMoreMaterials.addEventListener('click', function() {
        materialPage++;
        loadMaterials(true);
    });

    // 8. Otomatisasi Judul Materi dari Nama File Pertama
    const materialFileInput = document.getElementById('materialAttachments');
    const materialTitleInput = document.getElementById('materialTitle');
    
    materialFileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0 && materialTitleInput.value.trim() === '') {
            let fileName = this.files[0].name;
            // Buang ekstensi
            let title = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
            materialTitleInput.value = title;
        }
    });

    // 9. Penanganan Form Tambah Tugas
    formTask.addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btnSubmitTask');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = 'Menyimpan...';

        let formData = new FormData(this);
        formData.append('action', 'create');

        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert(res.message); // Notifikasi sukses (+1 Poin)
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                this.reset();
                document.getElementById('inputTaskSemesterId').value = currentSemesterId;
                if (currentTab === 'tasks') loadTasks(false);
            } else {
                alert('Gagal: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Submit Error:', err);
            alert('Terjadi kesalahan server saat menyimpan tugas.');
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Simpan Tugas (+1 Poin)';
        });
    });

    // 10. Penanganan Form Tambah Materi
    formMaterial.addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btnSubmitMaterial');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = 'Mengunggah...';

        let formData = new FormData(this);
        formData.append('action', 'create');

        fetch(`${BASE_URL}app/api/materials.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert(res.message); // Notifikasi sukses (+1 Poin)
                bootstrap.Modal.getInstance(document.getElementById('materialModal')).hide();
                this.reset();
                document.getElementById('inputMaterialSemesterId').value = currentSemesterId;
                if (currentTab === 'materials') loadMaterials(false);
            } else {
                alert('Gagal: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Submit Error:', err);
            alert('Terjadi kesalahan server saat menyimpan materi.');
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Bagikan Materi (+1 Poin)';
        });
    });

    // Fungsi Utilitas
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
             .toString()
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

    // Inisialisasi Pertama
    loadCourseHeader();
    updateSortOptions('tasks');
    loadTasks(false);
    
});