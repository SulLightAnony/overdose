/**
 * ====================================================================================
 * MODULE: Lupa Absensi Room Page Logic
 * FILE LOCATION: public/js/modules/forgotten_attendance_room.js
 * ====================================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    const params   = new URLSearchParams(window.location.search);
    const courseId = parseInt(params.get('course_id') || '0', 10);

    const elLoading       = document.getElementById('laLoading');
    const elContent       = document.getElementById('laContent');
    const elAlert         = document.getElementById('laAlert');
    const elSubtitle      = document.getElementById('laSubtitle');
    const elCourseName    = document.getElementById('laCourseName');
    const elCourseTime    = document.getElementById('laCourseTime');
    const elStudentList   = document.getElementById('laStudentList');
    const elStudentCount  = document.getElementById('laStudentCount');
    const elActionButtons = document.getElementById('laActionButtons');
    const elBtnRegister   = document.getElementById('btnLupaAbsensi');
    const elRegisteredHint = document.getElementById('laRegisteredHint');
    const elBtnSalinTeks  = document.getElementById('btnSalinTeks');
    const elBtnBagikanLink = document.getElementById('btnBagikanLink');

    let roomData = null; // will hold full API response data
    let isCurrentlyRegistered = false;

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(message, type = 'danger') {
        if (elAlert) {
            elAlert.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    }

    /**
     * Renders the level badge identically to the Dashboard "Top Contributor" section.
     */
    function renderRoleBadge(role) {
        if (!role) return '';
        const lower = role.toLowerCase();
        if (lower === 'primordial') {
            return `<span class="badge mt-1" style="background: linear-gradient(135deg, #be9d30, #ffd13b, #aa771c); color: #ffffff; font-size: 0.65rem; font-weight: 600;">Primordial</span>`;
        }
        if (lower === 'sepuh') {
            return `<span class="badge bg-secondary text-white mt-1" style="font-size: 0.65rem;">Sepuh</span>`;
        }
        if (lower === 'keroco') {
            return `<span class="text-muted d-block" style="font-size: 0.72rem;">Keroco</span>`;
        }
        return '';
    }

    // ------------------------------------------------------------------
    // Render student list
    // ------------------------------------------------------------------
    function renderStudentList(students) {
        if (!elStudentList) return;
        if (!Array.isArray(students) || students.length === 0) {
            elStudentList.innerHTML = `<div class="text-center text-muted py-4 small">Belum ada mahasiswa yang mendaftar.</div>`;
            if (elStudentCount) elStudentCount.textContent = '0';
            return;
        }

        if (elStudentCount) elStudentCount.textContent = String(students.length);

        const defaultAvatar = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'public/assets/img/logo.png';
        let html = '';
        students.forEach((s, index) => {
            const avatar = s.avatarUrl ? s.avatarUrl : defaultAvatar;
            html += `
                <div class="d-flex align-items-center gap-3 p-2 rounded-3 mb-2 border bg-white">
                    <span class="text-muted small fw-semibold flex-shrink-0" style="min-width: 24px;">${index + 1}.</span>
                    <img src="${escapeHtml(avatar)}" alt="${escapeHtml(s.name)}" class="rounded-circle border flex-shrink-0" style="width: 40px; height: 40px; object-fit: cover;" referrerpolicy="no-referrer">
                    <div class="min-w-0 flex-grow-1">
                        <h6 class="mb-0 fw-semibold text-dark small">${escapeHtml(s.name)}</h6>
                        ${renderRoleBadge(s.roleLevel)}
                    </div>
                </div>`;
        });
        elStudentList.innerHTML = html;
    }

    // ------------------------------------------------------------------
    // Update register button state
    // ------------------------------------------------------------------
    function updateRegisterButton(alreadyRegistered) {
        if (!elBtnRegister) return;
        isCurrentlyRegistered = alreadyRegistered;

        if (alreadyRegistered) {
            elBtnRegister.disabled = false;
            elBtnRegister.innerHTML = '<i class="bi bi-x-circle me-2"></i>Batalkan';
            elBtnRegister.classList.remove('btn-danger', 'btn-success');
            elBtnRegister.classList.add('btn-outline-danger');
            if (elRegisteredHint) elRegisteredHint.style.display = '';
        } else {
            elBtnRegister.disabled = false;
            elBtnRegister.innerHTML = 'Aku Lupa Absen😭';
            elBtnRegister.classList.remove('btn-outline-danger', 'btn-success');
            elBtnRegister.classList.add('btn-danger');
            if (elRegisteredHint) elRegisteredHint.style.display = 'none';
        }
    }

    // ------------------------------------------------------------------
    // Show "inactive room" modal and redirect on close
    // ------------------------------------------------------------------
    function showInactiveModal() {
        if (elLoading) elLoading.style.display = 'none';
        if (elContent) elContent.style.display = 'none';
        const modalEl = document.getElementById('roomInactiveModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    // ------------------------------------------------------------------
    // Load room data from API
    // ------------------------------------------------------------------
    async function loadRoomData(autoRegister = false) {
        if (!courseId) {
            showAlert('Parameter course_id tidak ditemukan di URL.');
            if (elLoading) elLoading.style.display = 'none';
            return;
        }

        try {
            const resp = await fetch(`${BASE_URL}api/forgotten-attendances?action=get_room_info&course_id=${courseId}`);
            const text = await resp.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (err) {
                throw new Error('Server mengembalikan respons yang tidak valid.');
            }

            if (!result.success) {
                showAlert(result.message || 'Gagal memuat data room.');
                if (elLoading) elLoading.style.display = 'none';
                return;
            }

            roomData = result.data;

            // Room inactive check
            if (!roomData.isActive) {
                showInactiveModal();
                return;
            }

            // Populate UI
            const course = roomData.course || {};
            const startTime = (course.startTime || '').substring(0, 5);
            const endTime   = (course.endTime || '').substring(0, 5);
            const timeRange = (startTime && endTime) ? `${startTime} - ${endTime}` : 'Waktu TBA';

            if (elCourseName)  elCourseName.textContent  = course.courseTitle || '-';
            if (elCourseTime)  elCourseTime.textContent  = `${course.courseDay || '-'} | ${timeRange}`;
            if (elSubtitle)    elSubtitle.textContent     = `Room aktif untuk ${course.courseTitle || '-'}`;
            document.title = `Lupa Absensi - ${course.courseTitle || '-'} — Overdose`;

            renderStudentList(roomData.students || []);
            updateRegisterButton(roomData.alreadyRegistered || false);

            // Show content
            if (elLoading)       elLoading.style.display       = 'none';
            if (elContent)       elContent.style.display        = '';
            if (elActionButtons) elActionButtons.style.removeProperty('display');

            // Auto-register via shared link if not already registered
            if (autoRegister && !roomData.alreadyRegistered) {
                await doRegister(true);
            }

        } catch (error) {
            showAlert(error.message || 'Terjadi kesalahan saat memuat data.');
            if (elLoading) elLoading.style.display = 'none';
        }
    }

    // ------------------------------------------------------------------
    // Register action
    // ------------------------------------------------------------------
    async function doRegister(silent = false) {
        if (!elBtnRegister) return;
        const originalHtml = elBtnRegister.innerHTML;
        elBtnRegister.disabled = true;
        elBtnRegister.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';

        try {
            const resp = await fetch(`${BASE_URL}api/forgotten-attendances?action=register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ course_id: courseId })
            });
            const result = await resp.json();

            if (result.success) {
                if (!silent) window.appToast?.('Berhasil mendaftarkan lupa absensi.', 'success');
                // Refresh list to get updated data
                await loadRoomData(false);
            } else if (result.alreadyRegistered) {
                updateRegisterButton(true);
            } else {
                if (!silent) showAlert(result.message || 'Gagal mendaftar.');
                elBtnRegister.disabled = false;
                elBtnRegister.innerHTML = originalHtml;
            }
        } catch (error) {
            showAlert('Terjadi kesalahan pada server.');
            elBtnRegister.disabled = false;
            elBtnRegister.innerHTML = originalHtml;
        }
    }

    // ------------------------------------------------------------------
    // Unregister action (Cancel attendance for logged-in user only)
    // ------------------------------------------------------------------
    async function doUnregister() {
        if (!elBtnRegister) return;
        const originalHtml = elBtnRegister.innerHTML;
        elBtnRegister.disabled = true;
        elBtnRegister.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';

        try {
            const resp = await fetch(`${BASE_URL}api/forgotten-attendances?action=unregister`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ course_id: courseId })
            });
            const result = await resp.json();

            if (result.success) {
                window.appToast?.('Berhasil membatalkan pendaftaran.', 'success');
                await loadRoomData(false);
            } else {
                showAlert(result.message || 'Gagal membatalkan pendaftaran.');
                elBtnRegister.disabled = false;
                elBtnRegister.innerHTML = originalHtml;
            }
        } catch (error) {
            showAlert('Terjadi kesalahan pada server.');
            elBtnRegister.disabled = false;
            elBtnRegister.innerHTML = originalHtml;
        }
    }

    // ------------------------------------------------------------------
    // "Salin Teks" button — copy formatted student list
    // ------------------------------------------------------------------
    if (elBtnSalinTeks) {
        elBtnSalinTeks.addEventListener('click', async () => {
            if (!roomData) return;
            const course   = roomData.course || {};
            const students = roomData.students || [];

            // class_name appears only once in the header
            const className  = (course.classGroup || (students[0]?.className) || '-');
            const courseName = course.courseTitle || '-';

            let text = `Daftar mahasiswa kelas ${className} yang telat melakukan absensi ${courseName}:\n`;
            if (students.length === 0) {
                text += '(Belum ada mahasiswa yang mendaftar)';
            } else {
                students.forEach((s, i) => {
                    text += `${i + 1}. ${s.name}\n`;
                });
            }

            try {
                await navigator.clipboard.writeText(text.trim());
                window.appToast?.('Teks berhasil disalin', 'success');
            } catch (err) {
                window.appToast?.('Gagal menyalin teks.', 'warning');
            }
        });
    }

    // ------------------------------------------------------------------
    // "Bagikan Link" button — copy room URL
    // ------------------------------------------------------------------
    if (elBtnBagikanLink) {
        elBtnBagikanLink.addEventListener('click', async () => {
            const url = `${BASE_URL}lupa-absensi?course_id=${courseId}&ref=share`;
            try {
                await navigator.clipboard.writeText(url);
                window.appToast?.('Link berhasil disalin', 'success');
            } catch (err) {
                window.appToast?.('Gagal menyalin link.', 'warning');
            }
        });
    }

    // ------------------------------------------------------------------
    // Main register / cancel button click handler
    // ------------------------------------------------------------------
    if (elBtnRegister) {
        elBtnRegister.addEventListener('click', () => {
            if (isCurrentlyRegistered) {
                const modalEl = document.getElementById('cancelConfirmModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            } else {
                doRegister(false);
            }
        });
    }

    // Modal confirm cancel button handler
    const btnConfirmCancel = document.getElementById('btnConfirmCancel');
    if (btnConfirmCancel) {
        btnConfirmCancel.addEventListener('click', async () => {
            const modalEl = document.getElementById('cancelConfirmModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            await doUnregister();
        });
    }

    // ------------------------------------------------------------------
    // Init: determine if page was opened via shared link (auto-register flag)
    // - If from=schedule: user clicked button on schedule page -> DO NOT auto-register.
    // - If ref=share or opened directly via share link -> AUTO-REGISTER.
    // ------------------------------------------------------------------
    const fromParam = params.get('from');
    const refParam  = params.get('ref');
    const isSharedLink = (refParam === 'share') || (params.has('course_id') && fromParam !== 'schedule');

    loadRoomData(isSharedLink);
});
