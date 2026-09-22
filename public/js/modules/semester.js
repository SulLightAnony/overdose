document.addEventListener("DOMContentLoaded", () => {
    fetchSemesters();

    const form = document.getElementById("formSemester");
    if (form) {
        form.addEventListener("submit", handleFormSubmit);
    }
});

let canManageSemester = false;
let semesterModalInstance = null;

async function fetchSemesters() {
    const grid = document.getElementById("semester-grid");
    const managerActions = document.getElementById("manager-actions");

    try {
        const fetchUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/semesters.php';
        const response = await fetch(fetchUrl);
        const res = await response.json();

        if (res.success && res.data) {
            canManageSemester = res.canManage || false;

            if (canManageSemester && managerActions) {
                managerActions.classList.remove("d-none");
            }

            if (res.data.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-journal-x fs-1 text-secondary d-block mb-2"></i>
                        <h6 class="fw-bold text-dark">Belum ada semester yang terdaftar.</h6>
                        <p class="text-muted small">${canManageSemester ? 'Klik tombol "Tambah Semester" di atas untuk menambahkan semester baru.' : 'Tunggu Sepuh/Primordial menambahkan semester.'}</p>
                    </div>`;
                return;
            }

            grid.innerHTML = "";
            res.data.forEach(sem => {
                const bg = sem.backgroundColor || '#3b82f6';
                const detailUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'semester/courses?id=' + sem.semesterId;

                let actionButtons = '';
                if (canManageSemester) {
                    actionButtons = `
                        <div class="position-absolute top-0 end-0 m-3 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-light bg-white border-0 shadow-sm rounded-circle p-1" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;" onclick="openEditModal(${sem.semesterId}, ${sem.semesterNumber}, '${escapeQuotes(sem.semesterTitle)}', '${sem.backgroundColor}')" title="Edit">
                                <i class="bi bi-pencil-fill text-dark style-icon" style="font-size:0.75rem;"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light bg-white border-0 shadow-sm rounded-circle p-1" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;" onclick="deleteSemester(${sem.semesterId})" title="Hapus">
                                <i class="bi bi-trash-fill text-danger style-icon" style="font-size:0.75rem;"></i>
                            </button>
                        </div>`;
                }

                grid.innerHTML += `
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-white position-relative hover-shadow transition-all h-100" style="background-color: ${bg}; min-height: 140px; display: flex; flex-direction: column; justify-content: space-between;">
                            ${actionButtons}
                            <a href="${detailUrl}" class="text-white text-decoration-none d-block h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-2.5 py-1 mb-2 fw-medium" style="font-size: 0.72rem;">
                                        Semester ${sem.semesterNumber}
                                    </span>
                                    <h5 class="fw-bold mb-0 text-white fs-5 lh-sm">${sem.semesterTitle}</h5>
                                </div>
                                <div class="mt-3 text-white-50 small fw-semibold d-flex align-items-center">
                                    Lihat Mata Kuliah<i class="bi bi-arrow-right ms-1"></i>
                                </div>
                            </a>
                        </div>
                    </div>`;
            });
        } else {
            showErrorState(res.message);
        }
    } catch (err) {
        console.error("Gagal mengambil data semester:", err);
        showErrorState("Gagal menghubungkan ke server.");
    }
}

function escapeQuotes(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function showErrorState(msg) {
    const grid = document.getElementById("semester-grid");
    if (grid) {
        grid.innerHTML = `
            <div class="col-12 text-center py-4 text-danger small">
                <i class="bi bi-exclamation-triangle fs-3 d-block mb-1"></i>
                ${msg}
            </div>`;
    }
}

function openAddModal() {
    document.getElementById("modalSemesterTitle").textContent = "Tambah Semester";
    document.getElementById("semesterId").value = "";
    document.getElementById("formMethod").value = "POST";
    document.getElementById("semesterNumber").value = "";
    document.getElementById("semesterTitle").value = "";
    document.getElementById("backgroundColor").value = "#3b82f6";
}

function openEditModal(id, number, title, bg) {
    document.getElementById("modalSemesterTitle").textContent = "Edit Semester";
    document.getElementById("semesterId").value = id;
    document.getElementById("formMethod").value = "PUT";
    document.getElementById("semesterNumber").value = number;
    document.getElementById("semesterTitle").value = title;
    document.getElementById("backgroundColor").value = bg || "#3b82f6";

    const modalEl = document.getElementById("modalSemester");
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const btnSave = document.getElementById("btnSaveSemester");
    const originalText = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...`;

    const form = e.target;
    const formData = new FormData(form);
    const method = formData.get("_method") || "POST";

    try {
        const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/semesters.php';
        let response;

        if (method === "PUT") {
            const bodyObj = {
                semesterId: formData.get("semesterId"),
                semesterNumber: formData.get("semesterNumber"),
                semesterTitle: formData.get("semesterTitle"),
                backgroundColor: formData.get("backgroundColor")
            };

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
            const modalEl = document.getElementById("modalSemester");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            fetchSemesters();
        } else {
            alert(res.message || "Gagal menyimpan semester.");
        }
    } catch (err) {
        console.error("Error submitting form:", err);
        alert("Terjadi kesalahan jaringan.");
    } finally {
        btnSave.disabled = false;
        btnSave.innerHTML = originalText;
    }
}

async function deleteSemester(id) {
    if (!confirm("Apakah kamu yakin ingin menghapus semester ini?")) return;

    try {
        const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'app/api/semesters.php';
        const response = await fetch(apiUrl, {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ semesterId: id })
        });

        const res = await response.json();
        if (res.success) {
            fetchSemesters();
        } else {
            alert(res.message || "Gagal menghapus semester.");
        }
    } catch (err) {
        console.error("Error deleting semester:", err);
        alert("Terjadi kesalahan jaringan.");
    }
}