document.addEventListener("DOMContentLoaded", () => {
    fetchCourses();

    const form = document.getElementById("formCourse");
    if (form) {
        form.addEventListener("submit", handleFormSubmit);
    }
});

let canManageCourse = false;
let courseToDelete = null;
const expectedCoursePhrase = `Saya ${typeof USER_NAME !== 'undefined' ? USER_NAME : ''} mengerti bahwa dengan menghapus mata kuliah ini maka seluruh tugas di dalamnya akan ikut terhapus.`;

async function fetchCourses() {
    const grid = document.getElementById("course-grid");
    const managerActions = document.getElementById("manager-actions");

    try {
        const fetchUrl = `${typeof BASE_URL !== 'undefined' ? BASE_URL : ''}app/api/courses.php?semesterId=${CURRENT_SEMESTER_ID}`;
        const response = await fetch(fetchUrl);
        const res = await response.json();

        if (res.success && res.data) {
            canManageCourse = res.canManage || false;

            if (canManageCourse && managerActions) {
                managerActions.classList.remove("d-none");
            }

            if (res.data.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-journal-x fs-1 text-secondary d-block mb-2"></i>
                        <h6 class="fw-bold text-dark">Belum ada mata kuliah.</h6>
                        <p class="text-muted small">${canManageCourse ? 'Klik tombol "Tambah Matkul" di atas untuk menambah data.' : 'Tunggu Sepuh/Primordial menambahkan mata kuliah.'}</p>
                    </div>`;
                return;
            }

            grid.innerHTML = "";
            res.data.forEach(course => {
                const bg = course.backgroundColor || '#10b981';
                const detailUrl = `${typeof BASE_URL !== 'undefined' ? BASE_URL : ''}tasks?courseId=${course.courseId}`;

                let actionButtons = '';
                if (canManageCourse) {
                    const safeCourse = JSON.stringify(course).replace(/"/g, '&quot;');
                    actionButtons = `
                        <div class="position-absolute top-0 end-0 m-3 d-flex gap-1 z-3">
                            <button type="button" class="btn btn-sm btn-light bg-white border-0 shadow-sm rounded-circle p-1 style-icon" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;" onclick="openEditModal(${safeCourse})" title="Edit">
                                <i class="bi bi-pencil-fill text-dark" style="font-size:0.75rem;"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light bg-white border-0 shadow-sm rounded-circle p-1 style-icon" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;" onclick="confirmDelete(${course.courseId})" title="Hapus">
                                <i class="bi bi-trash-fill text-danger" style="font-size:0.75rem;"></i>
                            </button>
                        </div>`;
                }

                const emailBtn = course.lecturerEmail ? `<a href="mailto:${course.lecturerEmail}" class="text-white text-opacity-75 hover-white style-icon"><i class="bi bi-envelope fs-5"></i></a>` : '';
                const waNumber = course.lecturerPhone ? course.lecturerPhone.replace(/\D/g, '') : '';
                const waBtn = waNumber ? `<a href="https://wa.me/${waNumber}" target="_blank" class="text-white text-opacity-75 hover-white style-icon"><i class="bi bi-whatsapp fs-5"></i></a>` : '';

                const courseTypeBadge = (course.courseType === 'Praktek') 
                    ? `<span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 mb-2 fw-semibold" style="font-size: 0.7rem;">Praktek</span>`
                    : `<span class="badge bg-white bg-opacity-25 text-white rounded-pill px-2.5 py-1 mb-2 fw-medium" style="font-size: 0.7rem;">Teori</span>`;

                grid.innerHTML += `
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card card-gradient shadow-sm rounded-4 p-4 text-white h-100 d-flex flex-column" style="--card-bg: ${bg}; position: relative;">
                            ${actionButtons}
                            
                            <div class="mb-3 flex-grow-1">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-2.5 py-1 mb-2 fw-medium" style="font-size: 0.72rem;">
                                        ${course.courseCode}
                                    </span>
                                    ${courseTypeBadge}
                                </div>
                                <h5 class="fw-bold mb-1 text-white fs-5 lh-sm pe-4">${course.courseTitle}</h5>
                                <p class="small text-white-50 mb-0 line-clamp-2">${course.courseDescription || 'Tidak ada deskripsi.'}</p>
                            </div>

                            <div class="mt-auto pt-3 border-top border-white border-opacity-25">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-person-circle fs-5 me-2 text-white-50"></i>
                                    <div class="min-w-0">
                                        <div class="small fw-semibold text-truncate">${course.lecturerName || 'Dosen Belum Diatur'}</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="${detailUrl}" class="text-white text-decoration-none small fw-semibold hover-white">
                                        Lihat Tugas <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                    <div class="d-flex gap-2">
                                        ${emailBtn}
                                        ${waBtn}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
            });
        } else {
            showErrorState(res.message);
        }
    } catch (err) {
        console.error("Gagal mengambil data matkul:", err);
        showErrorState("Gagal menghubungkan ke server.");
    }
}

function showErrorState(msg) {
    const grid = document.getElementById("course-grid");
    if (grid) {
        grid.innerHTML = `
            <div class="col-12 text-center py-4 text-danger small">
                <i class="bi bi-exclamation-triangle fs-3 d-block mb-1"></i>
                ${msg}
            </div>`;
    }
}

function openAddModal() {
    document.getElementById("modalCourseTitle").textContent = "Tambah Mata Kuliah";
    document.getElementById("courseId").value = "";
    document.getElementById("formMethod").value = "POST";
    
    document.getElementById("courseCode").value = "";
    document.getElementById("courseTitle").value = "";
    document.getElementById("courseType").value = "Teori";
    document.getElementById("courseDescription").value = "";
    document.getElementById("lecturerName").value = "";
    document.getElementById("lecturerEmail").value = "";
    document.getElementById("lecturerPhone").value = "";
    document.getElementById("backgroundColor").value = "#10b981";

    const modal = new bootstrap.Modal(document.getElementById("modalCourse"));
    modal.show();
}

function openEditModal(course) {
    document.getElementById("modalCourseTitle").textContent = "Edit Mata Kuliah";
    document.getElementById("courseId").value = course.courseId;
    document.getElementById("formMethod").value = "PUT";
    
    document.getElementById("courseCode").value = course.courseCode;
    document.getElementById("courseTitle").value = course.courseTitle;
    document.getElementById("courseType").value = course.courseType || "Teori";
    document.getElementById("courseDescription").value = course.courseDescription || "";
    document.getElementById("lecturerName").value = course.lecturerName || "";
    document.getElementById("lecturerEmail").value = course.lecturerEmail || "";
    document.getElementById("lecturerPhone").value = course.lecturerPhone || "";
    document.getElementById("backgroundColor").value = course.backgroundColor || "#10b981";

    const modal = new bootstrap.Modal(document.getElementById("modalCourse"));
    modal.show();
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const btnSave = document.getElementById("btnSaveCourse");
    const originalText = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...`;

    const form = e.target;
    const formData = new FormData(form);
    const method = formData.get("_method") || "POST";

    try {
        const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/courses.php';
        let response;

        if (method === "PUT") {
            const bodyObj = Object.fromEntries(formData.entries());
            response = await fetch(apiUrl, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(bodyObj)
            });
        } else {
            response = await fetch(apiUrl, {
                method: "POST",
                body: formData
            });
        }

        const res = await response.json();
        if (res.success) {
            const modalEl = document.getElementById("modalCourse");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            fetchCourses();
        } else {
            alert(res.message || "Gagal menyimpan mata kuliah.");
        }
    } catch (err) {
        console.error("Error submitting form:", err);
        alert("Terjadi kesalahan jaringan.");
    } finally {
        btnSave.disabled = false;
        btnSave.innerHTML = originalText;
    }
}

function confirmDelete(id) {
    courseToDelete = id;
    const inputConfirm = document.getElementById("confirmDeleteText");
    const btnConfirm = document.getElementById("btnConfirmDelete");
    
    if (inputConfirm) inputConfirm.value = "";
    if (btnConfirm) btnConfirm.disabled = true;

    const modal = new bootstrap.Modal(document.getElementById("modalDeleteConfirm"));
    modal.show();
}

document.getElementById("confirmDeleteText")?.addEventListener("input", function() {
    const btn = document.getElementById("btnConfirmDelete");
    if (this.value === expectedCoursePhrase) {
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
});

document.getElementById("btnConfirmDelete")?.addEventListener("click", async function() {
    if (!courseToDelete) return;

    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

    try {
        const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/courses.php';
        const response = await fetch(apiUrl, {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ courseId: courseToDelete })
        });

        const res = await response.json();
        if (res.success) {
            const modalEl = document.getElementById("modalDeleteConfirm");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            fetchCourses();
        } else {
            alert(res.message || "Gagal menghapus mata kuliah.");
        }
    } catch (err) {
        console.error("Error deleting course:", err);
        alert("Terjadi kesalahan jaringan.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        courseToDelete = null;
    }
});