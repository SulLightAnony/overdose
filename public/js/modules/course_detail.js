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
    const btnAddTask = document.getElementById('btnAddTask');
    const btnAddMaterial = document.getElementById('btnAddMaterial');
    
    const tasksListContainer = document.getElementById('tasks-list-container');
    const btnLoadMoreTasks = document.getElementById('btn-load-more-tasks');
    const tasksLoadingSpinner = document.getElementById('tasks-loading-spinner');
    
    const materialsListContainer = document.getElementById('materials-list-container');
    const btnLoadMoreMaterials = document.getElementById('btn-load-more-materials');
    const materialsLoadingSpinner = document.getElementById('materials-loading-spinner');

    const formTask = document.getElementById('formTask');
    const formMaterial = document.getElementById('formMaterial');

    // 1. Fetch Header Course (Desain Card Gradient Full-Width Mirip Halaman Courses)
    function loadCourseHeader() {
        fetch(`${BASE_URL}app/api/courses.php?action=detail&courseId=${CURRENT_COURSE_ID}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    const c = res.data;
                    const returnUrl = resolveCourseReturnUrl(c.semesterId);
                    currentSemesterId = c.semesterId || '';
                    const taskSemesterInput = document.getElementById('inputTaskSemesterId');
                    const materialSemesterInput = document.getElementById('inputMaterialSemesterId');
                    if (taskSemesterInput) taskSemesterInput.value = currentSemesterId;
                    if (materialSemesterInput) materialSemesterInput.value = currentSemesterId;

                    const bg = c.backgroundColor || '#10b981';

                    // Format Kontak Dosen (Tombol Aksi)
                    const emailBtn = c.lecturerEmail 
                        ? `<a href="mailto:${escapeHtml(c.lecturerEmail)}" class="text-white text-opacity-75 hover-white style-icon" title="${escapeHtml(c.lecturerEmail)}"><i class="bi bi-envelope fs-5"></i></a>` 
                        : '';
                    const waNumber = c.lecturerPhone ? c.lecturerPhone.replace(/\D/g, '') : '';
                    const waBtn = waNumber 
                        ? `<a href="https://wa.me/${waNumber}" target="_blank" class="text-white text-opacity-75 hover-white style-icon" title="${escapeHtml(c.lecturerPhone)}"><i class="bi bi-whatsapp fs-5"></i></a>` 
                        : '';

                    // Format Jadwal & Tipe
                    const timeDisplay = c.startTime && c.endTime ? `${c.startTime.substring(0,5)} - ${c.endTime.substring(0,5)}` : 'Waktu TBA';
                    const dayDisplay = c.courseDay ? c.courseDay : 'Hari TBA';
                    const courseTypeBadge = (c.courseType === 'Praktek') 
                        ? `<span class="badge bg-white bg-opacity-25 text-white px-3 py-1 fw-bold shadow-sm" style="font-size: 0.85rem;">PRAKTEK</span>`
                        : `<span class="badge bg-white bg-opacity-25 text-white px-3 py-1 fw-bold shadow-sm" style="font-size: 0.85rem;">TEORI</span>`;

                    headerContainer.innerHTML = `
                        <div class="mb-3">
                            <a href="${returnUrl.url}" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </a>
                        </div>
                        <div class="card card-gradient shadow-sm rounded-4 p-4 text-white w-100" style="--card-bg: ${bg}; position: relative;">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                        <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-2.5 py-1 fw-medium" style="font-size: 0.75rem;">
                                            ${escapeHtml(c.courseCode)}
                                        </span>
                                        ${c.courseClass ? `<span class="badge bg-white bg-opacity-25 text-white rounded-pill px-2.5 py-1 fw-medium" style="font-size: 0.75rem;">Kelas ${escapeHtml(c.courseClass)}</span>` : ''}
                                    </div>
                                    <h3 class="fw-bold mb-2 text-white lh-sm">${escapeHtml(c.courseTitle)}</h3>
                                    <div class="mb-2">${courseTypeBadge}</div>
                                    <p class="text-white-50 mb-0">${escapeHtml(c.courseDescription || 'Tidak ada deskripsi.')}</p>
                                </div>
                                <div class="text-end d-none d-md-block ms-auto">
                                    <i class="bi bi-book-half display-4 text-white opacity-25"></i>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-white border-opacity-25">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-7">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-calendar-event fs-6 me-2 text-white-50"></i>
                                            <div class="small fw-medium">${dayDisplay}, ${timeDisplay}</div>
                                        </div>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-person-circle fs-6 me-2 text-white-50"></i>
                                            <div class="small fw-semibold text-truncate">${escapeHtml(c.lecturerName || 'Dosen Belum Diatur')}</div>
                                        </div>
                                        <div class="d-flex align-items-center flex-wrap gap-3 small text-white-50">
                                            ${c.lecturerPhone ? `<div><i class="bi bi-telephone me-1"></i><span>${escapeHtml(c.lecturerPhone)}</span></div>` : ''}
                                            ${c.lecturerEmail ? `<div><i class="bi bi-envelope me-1"></i><span>${escapeHtml(c.lecturerEmail)}</span></div>` : ''}
                                        </div>
                                    </div>
                                    <div class="col-md-5 text-md-end mt-2 mt-md-0">
                                        <div class="d-flex justify-content-md-end gap-3 align-items-center">
                                            ${emailBtn}
                                            ${waBtn}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    headerContainer.innerHTML = `<div class="alert alert-danger shadow-sm rounded-4">Gagal memuat informasi mata kuliah.</div>`;
                }
            })
            .catch(err => {
                console.error('Error fetching course detail:', err);
                headerContainer.innerHTML = `<div class="alert alert-danger shadow-sm rounded-4">Terjadi kesalahan koneksi.</div>`;
            });
    }

    function resolveCourseReturnUrl(semesterId) {
        const params = new URLSearchParams(window.location.search);
        let source = params.get('from');
        let referrerUrl = null;

        if (document.referrer) {
            try {
                referrerUrl = new URL(document.referrer);
            } catch (error) {
                referrerUrl = null;
            }
        }

        if (!source && referrerUrl) {
            if (referrerUrl.pathname.endsWith('/schedule')) source = 'schedule';
            else if (referrerUrl.pathname.endsWith('/tasks')) source = 'global';
            else if (referrerUrl.pathname.endsWith('/semester/courses')) source = 'courses';
            else if (referrerUrl.pathname.endsWith('/dashboard') || referrerUrl.pathname === new URL(BASE_URL).pathname.replace(/\/$/, '')) source = 'dashboard';
        }

        if (source === 'dashboard') {
            return { url: `${BASE_URL}dashboard`, label: 'Kembali' };
        }

        if (source === 'schedule') {
            return { url: `${BASE_URL}schedule`, label: 'Kembali' };
        }

        if (source === 'global' || source === 'tasks') {
            const filter = params.get('filter') || referrerUrl?.searchParams.get('filter') || 'all';
            return { url: `${BASE_URL}tasks?filter=${encodeURIComponent(filter)}`, label: 'Kembali' };
        }

        if (source === 'courses' && referrerUrl) {
            const sourceSemesterId = referrerUrl.searchParams.get('id') || semesterId;
            return { url: `${BASE_URL}semester/courses?id=${encodeURIComponent(sourceSemesterId)}`, label: 'Kembali' };
        }

        return { url: `${BASE_URL}semester/courses?id=${encodeURIComponent(semesterId)}`, label: 'Kembali' };
    }

    // Navigasi dengan Penyimpanan Posisi Scroll untuk Restorasi Kembali
    function navigateToDetail(url) {
        const scrollPos = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        sessionStorage.setItem(`course_scroll_${CURRENT_COURSE_ID}`, scrollPos.toString());
        sessionStorage.setItem(`course_tab_${CURRENT_COURSE_ID}`, currentTab);
        sessionStorage.setItem(`course_restore_scroll_${CURRENT_COURSE_ID}`, '1');
        window.location.href = url;
    }
    window.navigateToDetail = navigateToDetail;

    function checkAndRestoreScroll() {
        const shouldRestore = sessionStorage.getItem(`course_restore_scroll_${CURRENT_COURSE_ID}`);
        const savedY = sessionStorage.getItem(`course_scroll_${CURRENT_COURSE_ID}`);
        if (shouldRestore === '1' && savedY !== null) {
            sessionStorage.removeItem(`course_restore_scroll_${CURRENT_COURSE_ID}`);
            const targetY = parseInt(savedY, 10);
            if (!isNaN(targetY) && targetY > 0) {
                if ('scrollRestoration' in history) {
                    history.scrollRestoration = 'manual';
                }
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        window.scrollTo({
                            top: targetY,
                            behavior: 'smooth'
                        });
                    }, 80);
                });
            }
        }
    }

    // 2. Fetch Tasks (Redesain Mirip Daftar Tugas Global)
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
                    const canHardDelete = Boolean(res.data.canHardDelete);
                    
                    if (tasks.length === 0 && taskPage === 1) {
                        tasksListContainer.innerHTML = `
                            <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                                <div class="card-body">
                                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                    <h6 class="fw-semibold text-dark mb-1">Tidak Ada Tugas</h6>
                                    <p class="text-muted small mb-0">Belum ada tugas untuk mata kuliah ini.</p>
                                </div>
                            </div>`;
                        return;
                    }

                    const now = new Date();

                    tasks.forEach(t => {
                        const isDeleted = parseInt(t.deletionStatus) === 1;
                        let cardHTML = '';
                        const truncatedDesc = truncateText(t.taskDescription, 100);

                        if (isDeleted) {
                            // Tampilan Soft-Delete (Disabled & Unclickable)
                            cardHTML = `
                                <div class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden bg-light mb-1" style="opacity: 0.6; cursor: not-allowed; border-left: 5px solid #6c757d !important;">
                                    <div class="card-body p-3 p-md-4">
                                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                            <i class="bi bi-mortarboard-fill text-muted me-1 align-middle fs-6"></i>
                                            <span class="fw-bold text-muted text-decoration-line-through fs-6 align-middle">${escapeHtml(t.taskTitle)}</span>
                                        </div>
                                        ${truncatedDesc ? `<p class="text-muted small mb-2">${escapeHtml(truncatedDesc)}</p>` : ''}
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1.5 fw-semibold small">
                                                <i class="bi bi-trash-fill me-1"></i>Dihapus oleh ${escapeHtml(t.deletedByUserName || 'Sistem')} pada ${escapeHtml(formatDateTime(t.deletedAt))}
                                            </span>
                                            ${canHardDelete ? `<button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-hard-delete-task="${Number(t.taskId)}">Hapus Permanen</button>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Tampilan Aktif
                            const isCompleted = Number(t.isCompleted) > 0;
                            const dueDate = t.dueDate ? new Date(t.dueDate.replace(/-/g, '/')) : null;
                            const isMissed = !isCompleted && dueDate && (dueDate < now);

                            let statusBadgeHtml = '';
                            let countdownBadgeHtml = '';

                            if (isCompleted) {
                                statusBadgeHtml = `<span class="badge bg-success text-white rounded-pill px-3 py-1.5 fw-medium"><i class="bi bi-check2-circle me-1"></i>Selesai</span>`;
                            } else if (isMissed) {
                                statusBadgeHtml = `<span class="badge bg-danger text-white rounded-pill px-3 py-1.5 fw-bold shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Terlewat</span>`;
                            } else {
                                statusBadgeHtml = `<span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 fw-medium"><i class="bi bi-clock-history me-1"></i>Pending</span>`;

                                if (dueDate) {
                                    const diffMs = dueDate.getTime() - now.getTime();
                                    const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
                                    const remainingText = formatRemainingTime(diffMs);

                                    if (totalHours <= 24) {
                                        countdownBadgeHtml = `<small class="text-danger fw-bold ms-1">(${escapeHtml(remainingText)})</small>`;
                                    } else if (totalHours <= 168) {
                                        countdownBadgeHtml = `<small class="fw-semibold ms-1" style="color: #d97706;">(${escapeHtml(remainingText)})</small>`;
                                    } else {
                                        countdownBadgeHtml = `<small class="text-success fw-medium ms-1">(${escapeHtml(remainingText)})</small>`;
                                    }
                                }
                            }

                            const borderLeftStyle = isMissed
                                ? 'border-left: 5px solid #dc3545 !important;'
                                : (isCompleted ? 'border-left: 5px solid #198754 !important;' : 'border-left: 5px solid #ffc107 !important;');

                            let taskTypeBadgeHtml = '';
                            if (t.taskType && t.taskType.trim().toLowerCase() !== 'tugas') {
                                taskTypeBadgeHtml = `<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 small fw-semibold align-middle">${escapeHtml(t.taskType)}</span>`;
                            }

                            const detailUrl = `${BASE_URL}task-detail?id=${t.taskId}&from=course`;

                            cardHTML = `
                                <div onclick="window.navigateToDetail('${detailUrl}')"
                                     class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden task-card-item mb-1"
                                     style="${borderLeftStyle}">
                                    <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                <i class="bi bi-mortarboard-fill text-primary me-1 align-middle fs-6"></i>
                                                <span class="fw-bold text-dark fs-6 align-middle">${escapeHtml(t.taskTitle)}</span>
                                                ${taskTypeBadgeHtml}
                                            </div>
                                            ${truncatedDesc ? `<p class="text-secondary small mb-2">${escapeHtml(truncatedDesc)}</p>` : ''}
                                            <div class="d-flex align-items-center flex-wrap gap-1">
                                                <small class="${isMissed ? 'text-danger fw-bold' : 'text-muted'} align-middle">
                                                    <i class="bi bi-calendar-event me-1"></i>${escapeHtml(formatDateTime(t.dueDate))}
                                                </small>
                                                ${countdownBadgeHtml}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            ${statusBadgeHtml}
                                            <div class="ms-1 text-secondary">
                                                <i class="bi bi-chevron-right fs-5"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        tasksListContainer.insertAdjacentHTML('beforeend', cardHTML);
                    });

                    if (res.data.hasMore) {
                        btnLoadMoreTasks.classList.remove('d-none');
                    }

                    if (!append) {
                        checkAndRestoreScroll();
                    }
                }
            })
            .catch(err => {
                tasksLoadingSpinner.classList.add('d-none');
                console.error('Error fetching tasks:', err);
            });
    }

    // 3. Fetch Materials (Redesain Struktur Serupa Task Card)
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
                        materialsListContainer.innerHTML = `
                            <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                                <div class="card-body">
                                    <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
                                    <h6 class="fw-semibold text-dark mb-1">Belum Ada Materi</h6>
                                    <p class="text-muted small mb-0">Belum ada materi pembelajaran untuk mata kuliah ini.</p>
                                </div>
                            </div>`;
                        return;
                    }

                    materials.forEach(m => {
                        const isDeleted = parseInt(m.deletionStatus) === 1;
                        let cardHTML = '';
                        const truncatedDesc = truncateText(m.materialDescription, 100);

                        if (isDeleted) {
                            cardHTML = `
                                <div class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden bg-light mb-1" style="opacity: 0.6; cursor: not-allowed; border-left: 5px solid #6c757d !important;">
                                    <div class="card-body p-3 p-md-4">
                                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                            <i class="bi bi-journal-text text-muted me-1 align-middle fs-6"></i>
                                            <span class="fw-bold text-muted text-decoration-line-through fs-6 align-middle">${escapeHtml(m.materialTitle)}</span>
                                        </div>
                                        ${truncatedDesc ? `<p class="text-muted small mb-2">${escapeHtml(truncatedDesc)}</p>` : ''}
                                        <div>
                                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1.5 fw-semibold small">
                                                <i class="bi bi-trash-fill me-1"></i>Dihapus oleh ${escapeHtml(m.deletedByUserName || 'Sistem')} pada ${escapeHtml(formatDateTime(m.deletedAt))}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            const detailUrl = `${BASE_URL}material-detail?id=${m.materialId}&from=course`;
                            cardHTML = `
                                <div onclick="window.navigateToDetail('${detailUrl}')"
                                     class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden task-card-item mb-1"
                                     style="border-left: 5px solid #10b981 !important;">
                                    <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                <i class="bi bi-journal-text text-success me-1 align-middle fs-6"></i>
                                                <span class="fw-bold text-dark fs-6 align-middle">${escapeHtml(m.materialTitle)}</span>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 small fw-semibold align-middle">Materi</span>
                                            </div>
                                            ${truncatedDesc ? `<p class="text-secondary small mb-2">${escapeHtml(truncatedDesc)}</p>` : ''}
                                            <div class="d-flex align-items-center flex-wrap gap-3">
                                                <small class="text-muted align-middle">
                                                    <i class="bi bi-calendar-event me-1"></i>${escapeHtml(formatDateTime(m.createdAt))}
                                                </small>
                                                <small class="text-muted align-middle">
                                                    <i class="bi bi-person me-1"></i>${escapeHtml(m.authorName || 'Pengguna')}
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-success text-white rounded-pill px-3 py-1.5 fw-medium">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Tersedia
                                            </span>
                                            <div class="ms-1 text-secondary">
                                                <i class="bi bi-chevron-right fs-5"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        materialsListContainer.insertAdjacentHTML('beforeend', cardHTML);
                    });

                    if (res.data.hasMore) {
                        btnLoadMoreMaterials.classList.remove('d-none');
                    }

                    if (!append) {
                        checkAndRestoreScroll();
                    }
                }
            })
            .catch(err => {
                materialsLoadingSpinner.classList.add('d-none');
                console.error('Error fetching materials:', err);
            });
    }

    // 4. Manajemen Tab & Opsi Sortir Dinamis (Serta Tombol Tambah Kontekstual)
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
        try {
            sessionStorage.setItem(`course_tab_${CURRENT_COURSE_ID}`, 'tasks');
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'tasks');
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
        if (btnAddTask) btnAddTask.classList.remove('d-none');
        if (btnAddMaterial) btnAddMaterial.classList.add('d-none');
        updateSortOptions('tasks');
        loadTasks(false);
    });

    tabMaterialsBtn.addEventListener('shown.bs.tab', function () {
        currentTab = 'materials';
        try {
            sessionStorage.setItem(`course_tab_${CURRENT_COURSE_ID}`, 'materials');
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
        if (btnAddTask) btnAddTask.classList.add('d-none');
        if (btnAddMaterial) btnAddMaterial.classList.remove('d-none');
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

    tasksListContainer.addEventListener('click', async event => {
        const button = event.target.closest('[data-hard-delete-task]');
        if (!button || !await window.appConfirm('Tugas dan seluruh data turunannya akan dihapus permanen.')) return;
        button.disabled = true;
        fetch(`${BASE_URL}app/api/tasks.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'hard_delete', taskId: button.dataset.hardDeleteTask })
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Tugas gagal dihapus permanen.');
            window.appToast?.('Tugas dihapus permanen.', 'success');
            loadTasks(false);
        }).catch(error => {
            window.appToast?.(error.message, 'danger');
            button.disabled = false;
        });
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
                window.appToast?.(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                this.reset();
                window.clearMultiFileInputs?.(this);
                document.getElementById('inputTaskSemesterId').value = currentSemesterId;
                if (currentTab === 'tasks') loadTasks(false);
            } else {
                window.appToast?.('Gagal: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Submit Error:', err);
            window.appToast?.('Terjadi kesalahan server saat menyimpan tugas.', 'danger');
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
                window.appToast?.(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('materialModal')).hide();
                this.reset();
                window.clearMultiFileInputs?.(this);
                document.getElementById('inputMaterialSemesterId').value = currentSemesterId;
                if (currentTab === 'materials') loadMaterials(false);
            } else {
                window.appToast?.('Gagal: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Submit Error:', err);
            window.appToast?.('Terjadi kesalahan server saat menyimpan materi.', 'danger');
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

    function truncateText(text, maxLength = 100) {
        if (!text) return '';
        const trimmed = String(text).trim();
        if (trimmed.length <= maxLength) return trimmed;
        return trimmed.substring(0, maxLength) + '...';
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const day = d.getDate();
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day} ${month} ${year}, ${hours}:${minutes}`;
    }

    function formatRemainingTime(diffMs) {
        if (diffMs <= 0) return 'Deadline Tiba';
        const totalMinutes = Math.floor(diffMs / (1000 * 60));
        const totalHours = Math.floor(totalMinutes / 60);
        const days = Math.floor(totalHours / 24);
        const hours = totalHours % 24;
        const minutes = totalMinutes % 60;

        let result = '';
        if (days > 0) {
            result += `${days} hari `;
            if (hours > 0) {
                result += `${hours} jam `;
            }
        } else if (hours > 0) {
            result += `${hours} jam `;
            if (minutes > 0) {
                result += `${minutes} menit `;
            }
        } else {
            result += `${minutes > 0 ? minutes : 1} menit `;
        }
        return `${result.trim()} lagi`;
    }

    function formatDate(dateString) {
        return formatDateTime(dateString);
    }

    // Inisialisasi Pertama
    loadCourseHeader();

    // Pulihkan tab aktif (dari URL parameter '?tab=...' atau sessionStorage)
    const urlParams = new URLSearchParams(window.location.search);
    const paramTab = urlParams.get('tab');
    const savedTab = paramTab || sessionStorage.getItem(`course_tab_${CURRENT_COURSE_ID}`);

    if (savedTab === 'materials') {
        const materialTabTrigger = bootstrap.Tab.getOrCreateInstance(tabMaterialsBtn);
        materialTabTrigger.show();
    } else {
        updateSortOptions('tasks');
        loadTasks(false);
    }
    
});