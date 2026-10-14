<?php
/**
 * ====================================================================================
 * MODULE: Frontend View Task Detail (Detail Tugas & Discussion Center)
 * FILE LOCATION: app/views/task_detail.php
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File ini menyediakan antarmuka pengguna (UI) lengkap untuk menampilkan detail sebuah tugas,
 * mengunduh/membuka multi-file lampiran pendukung dalam tata letak grid, menandai status
 * penyelesaian tugas, melihat daftar teman yang sudah selesai dengan opsi privasi,
 * membuka modal sharing jawaban secara terpadu di halaman yang sama, serta mengelola
 * diskusi/komentar publik dengan form bergaya Global Chat.
 * 
 * CATATAN INTEGRITAS:
 * - DILARANG menggunakan emoji di dalam baris kode PHP, JS, HTML, maupun CSS.
 * - Tetap menggunakan tabel task_shared_answers dan task_shared_answer_files di database.
 */

require_once __DIR__ . '/../api/auth/gatekeeper.php';

$taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pageTitle = 'Detail Tugas';
$activeMenu = 'course';

ob_start();
?>

<div class="container-fluid p-0" id="task-detail-container" data-task-id="<?= $taskId ?>">

    <!-- 1. Navigation & Breadcrumb Header -->
    <div class="mb-3">
        <a href="#" id="backToCourseBtn" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Alert Container (Untuk pesan Soft-Delete/Error) -->
    <div id="alert-container"></div>

    <!-- Skeleton Loader (Ditampilkan saat AJAX memuat data) -->
    <div id="skeleton-loader" class="placeholder-glow">
        <div class="card shadow-sm mb-4 rounded-4 border-0 p-4"><div class="card-body"><h3 class="placeholder col-6 rounded mb-3"></h3><p class="placeholder col-4 rounded"></p></div></div>
        <div class="card shadow-sm mb-4 rounded-4 border-0 p-4"><div class="card-body"><p class="placeholder col-8 rounded"></p></div></div>
    </div>

    <!-- Wrapper Konten Utama (Disembunyikan sampai data berhasil dimuat) -->
    <div id="main-content-wrapper" class="d-none">
        
        <!-- 1. Banner Utama Informasi Tugas (Desain Konsisten Mirip Course Detail Item Card) -->
        <div class="card border-0 shadow-sm rounded-4 text-dark overflow-hidden mb-4 p-4" id="taskBannerCard" style="border-left: 5px solid #ffc107 !important;">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div class="flex-grow-1">
                    <div class="text-secondary small fw-semibold mb-1" id="courseNameTitle"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <i class="bi bi-mortarboard-fill text-primary fs-3 me-1 align-middle" id="taskBannerIcon"></i>
                        <span class="fs-4 fw-bold text-dark align-middle" id="taskTitle"></span>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 small fw-semibold align-middle" id="taskTypeBadge"></span>
                        <span id="taskStatusBadgeContainer"></span>
                    </div>
                    <div class="small fw-semibold mt-1" id="taskDeadlineText"></div>
                </div>

                <!-- Menu Titik Tiga Aksi Banner di Sudut Kanan Atas -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-circle shadow-none p-0 d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 36px; height: 36px;" title="Menu Aksi">
                        <i class="bi bi-three-dots-vertical fs-5 text-secondary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                        <li>
                            <button class="dropdown-item py-2 small" type="button" data-share-url>
                                <i class="bi bi-share me-2"></i>Bagikan URL
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-2 small d-none" type="button" id="btnDropdownEditTask" data-bs-toggle="modal" data-bs-target="#editTaskModal">
                                <i class="bi bi-pencil me-2"></i>Edit Tugas
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-2 small text-danger d-none" type="button" id="btnDropdownDeleteTask">
                                <i class="bi bi-trash me-2"></i>Hapus Tugas
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Informasi Pembuat di Bagian Bawah Banner -->
            <div class="mt-3 pt-2 border-top border-light-subtle text-muted small" id="bannerAuthorInfo"></div>
        </div>

        <!-- 2. Deskripsi Tugas & Lampiran File (Satu Wrapper Card) -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Deskripsi Tugas</h5>
            <div id="taskDescription" class="text-secondary" style="white-space: pre-wrap; line-height: 1.6;"></div>

            <!-- Pemisah Garis Tipis Antara Deskripsi & Lampiran File -->
            <div id="taskFilesDivider" class="border-top border-light-subtle my-4 d-none"></div>

            <!-- Grid Lampiran File (Tanpa Teks Judul "Lampiran File") -->
            <div id="taskFilesGrid" class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4"></div>
            <div class="text-muted fst-italic d-none" id="noFilesText">Tidak ada lampiran file.</div>
        </div>

        <!-- 3. Tombol "Tandai Selesai" di Tengah Halaman -->
        <div class="d-flex justify-content-center my-4">
            <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" id="btnToggleComplete" style="min-width: 220px;">
                <span id="completeBtnText">Tandai Selesai</span>
            </button>
        </div>

        <!-- 5. Row Bawah: Mahasiswa Selesai & Komentar -->
        <div class="row">
            <!-- Kolom Kiri: Mahasiswa yang Telah Selesai -->
            <div class="col-lg-5 mb-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column">
                    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark">Mahasiswa yang Telah Selesai</span>
                        <span class="badge bg-success rounded-pill d-none" id="completionCountBadge"></span>
                    </div>

                    <div class="card-body p-0 flex-grow-1" style="max-height: 320px; overflow-y: auto;">
                        <ul class="list-group list-group-flush" id="completionsList">
                            <!-- Item mahasiswa akan dirender oleh JS -->
                        </ul>
                    </div>

                    <div class="p-3 border-top border-light bg-white rounded-bottom-4">
                        <div class="text-center text-muted mb-3" style="font-size: 0.8rem;">
                            Tidak ingin namamu muncul di sini? Atur melalui <a href="<?= BASE_URL ?>configurations" class="text-decoration-underline text-secondary fw-semibold">Pengaturan Profil</a>.
                        </div>

                        <!-- Tombol Bagikan Jawaban / Batal Bagikan Jawaban -->
                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold w-100" id="btnShareAnswer">
                            Bagikan Jawaban
                        </button>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Komentar -->
            <div class="col-lg-7 mb-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <span class="fw-bold text-dark">Komentar</span>
                    </div>

                    <!-- Container Komentar (Scrollable) -->
                    <div class="card-body p-3 flex-grow-1 overflow-auto" id="commentsContainer" style="max-height: 360px; min-height: 180px;">
                        <!-- Komentar akan dirender oleh JS -->
                    </div>

                    <!-- Input Form Komentar (Gaya Global Chat) -->
                    <div class="p-3 border-top border-light bg-white rounded-bottom-4">
                        <form id="commentForm" class="d-flex align-items-end gap-2">
                            <div class="flex-grow-1 position-relative">
                                <textarea id="commentContent" class="form-control rounded-4 px-3 py-2 shadow-none border-light bg-light" placeholder="Tulis komentar..." rows="1" style="resize: none; max-height: 80px; overflow-y: auto; font-size: 0.9rem;" required></textarea>
                            </div>
                            <button type="button" class="btn btn-light rounded-circle p-0 d-none align-items-center justify-content-center flex-shrink-0 shadow-sm" id="btnCancelEditComment" style="width: 42px; height: 42px;" title="Batal Edit">
                                <i class="bi bi-x-lg text-secondary fs-6"></i>
                            </button>
                            <button type="submit" class="btn btn-primary rounded-circle p-0 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm hover-lift" id="btnSubmitComment" style="width: 42px; height: 42px;" title="Kirim Komentar">
                                <i class="bi bi-send-fill fs-6" id="btnSubmitCommentIcon"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- Modal Form Edit Tugas -->
<!-- ============================================================ -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <form id="formEditTask" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editTaskModalLabel">Edit Tugas Kuliah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="taskId" value="<?= $taskId ?>">
                    <input type="hidden" name="courseId" id="editCourseId" value="">
                    
                    <div class="mb-3">
                        <label for="editTaskTitle" class="form-label fw-semibold">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="editTaskTitle" name="taskTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="editDueDate" class="form-label fw-semibold">Tenggat Waktu (Deadline) <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control rounded-3" id="editDueDate" name="dueDate" required>
                    </div>

                    <div class="mb-3">
                        <label for="editTaskDescription" class="form-label fw-semibold">Deskripsi / Instruksi Tugas</label>
                        <textarea class="form-control rounded-3" id="editTaskDescription" name="taskDescription" rows="5" style="resize: none;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lampiran</label>
                        <div id="editTaskExistingFiles" class="list-group rounded-3"></div>
                    </div>

                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <label for="editTaskAttachments" class="form-label fw-bold">Unggah Berkas</label>
                        <input class="form-control mb-2" type="file" id="editTaskAttachments" name="attachments[]" multiple>
                        <div class="form-text small">Maksimal 10 file, total akumulasi ukuran maksimal 15MB.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitEditTask">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal Form Upload/Bagikan Jawaban Baru -->
<!-- ============================================================ -->
<div class="modal fade" id="shareAnswerModal" tabindex="-1" aria-labelledby="shareAnswerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <form id="formShareAnswer" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="taskId" value="<?= $taskId ?>">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="shareAnswerModalLabel">Bagikan Jawaban Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="newAnswerTitle" class="form-label fw-semibold">Judul Jawaban <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="newAnswerTitle" name="answerTitle" placeholder="Contoh: Jawaban Lengkap Bab 1-3" required>
                    </div>

                    <div class="mb-3">
                        <label for="newAnswerNotes" class="form-label fw-semibold">Keterangan (Opsional)</label>
                        <textarea class="form-control rounded-3" id="newAnswerNotes" name="answerNotes" rows="4" style="resize: none;" placeholder="Tuliskan catatan tambahan mengenai file atau metode pengerjaan..."></textarea>
                    </div>

                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <label for="newAnswerAttachments" class="form-label fw-bold">Unggah Berkas</label>
                        <input class="form-control mb-2" type="file" id="newAnswerAttachments" name="attachments[]" multiple>
                        <div class="form-text small">Maksimal 10 file, total akumulasi ukuran maksimal 15MB.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4 fw-semibold" id="btnSubmitShareAnswer">Bagikan Jawaban</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal Lihat / Edit Jawaban -->
<!-- ============================================================ -->
<div class="modal fade" id="viewAnswerModal" tabindex="-1" aria-labelledby="viewAnswerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-2">
                <div class="d-flex align-items-center gap-2 flex-grow-1" id="modalAnswerAuthorHeader">
                    <img id="modalAnswerAuthorAvatar" src="" class="rounded-circle border" style="width: 38px; height: 38px; object-fit: cover;" alt="Avatar">
                    <div>
                        <div class="fw-bold text-dark fs-6" id="modalAnswerAuthorName"></div>
                        <div class="text-muted small" id="modalAnswerDateMeta"></div>
                    </div>
                </div>

                <!-- Menu Titik Tiga Jawaban -->
                <div class="dropdown me-2">
                    <button class="btn btn-sm btn-light rounded-circle shadow-none p-0 d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 34px; height: 34px;" title="Menu Jawaban">
                        <i class="bi bi-three-dots-vertical fs-6 text-secondary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                        <li>
                            <button class="dropdown-item py-2 small" type="button" id="btnCopyAnswerLink">
                                <i class="bi bi-share me-2"></i>Bagikan URL
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item py-2 small text-danger d-none" type="button" id="btnDeleteOwnAnswer">
                                <i class="bi bi-trash me-2"></i>Hapus Jawaban
                            </button>
                        </li>
                    </ul>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <!-- Konten Read-Only Jawaban (Untuk jawaban milik pengguna lain) -->
            <div id="readOnlyAnswerContent" class="modal-body p-4 pt-2">
                <h5 class="fw-bold text-dark mb-2" id="readOnlyAnswerTitle"></h5>
                <div class="text-secondary mb-4" id="readOnlyAnswerNotes" style="white-space: pre-wrap; line-height: 1.6;"></div>

                <div id="readOnlyAnswerFilesGrid" class="row g-3 row-cols-2 row-cols-md-3"></div>
                <div class="text-muted fst-italic d-none" id="noAnswerFilesText">Tidak ada file lampiran.</div>
            </div>

            <!-- Konten Editable Jawaban (Otomatis aktif jika jawaban milik sendiri) -->
            <form id="formEditAnswer" class="d-none" enctype="multipart/form-data">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="answerId" id="editAnswerIdHidden" value="">

                <div class="modal-body p-4 pt-2">
                    <div class="mb-3">
                        <label for="editAnswerTitleInput" class="form-label fw-semibold">Judul Jawaban <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="editAnswerTitleInput" name="answerTitle" required>
                    </div>

                    <div class="mb-3">
                        <label for="editAnswerNotesInput" class="form-label fw-semibold">Keterangan</label>
                        <textarea class="form-control rounded-3" id="editAnswerNotesInput" name="answerNotes" rows="4" style="resize: none;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lampiran</label>
                        <div id="editAnswerExistingFiles" class="list-group rounded-3 mb-2"></div>
                    </div>

                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <label for="editAnswerNewAttachments" class="form-label fw-bold">Unggah Berkas</label>
                        <input class="form-control mb-2" type="file" id="editAnswerNewAttachments" name="attachments[]" multiple>
                        <div class="form-text small">Maksimal 10 file, total akumulasi ukuran maksimal 15MB.</div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" id="btnSaveAnswerEdit" disabled>Simpan Perubahan</button>
                </div>
            </form>

            <div class="modal-footer border-0 pt-0" id="readOnlyAnswerFooter">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const CURRENT_TASK_ID = <?= $taskId ?>;
</script>
<script src="<?= BASE_URL ?>public/js/modules/task_detail.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>