document.addEventListener('DOMContentLoaded', () => {
    const activeSemesterTitle = document.getElementById('activeSemesterTitle');
    const activeSemesterInfo = document.getElementById('activeSemesterInfo');
    const activeSemesterBanner = document.getElementById('activeSemesterBanner');
    const dailyScheduleList = document.getElementById('dailyScheduleList');
    const scheduleAlert = document.getElementById('scheduleAlert');
    const btnDownloadSchedule = document.getElementById('btnDownloadSchedule');
    const btnExportPdf = document.getElementById('btnExportPdf');
    const btnExportPng = document.getElementById('btnExportPng');
    const scheduleExportArea = document.getElementById('scheduleExportArea');
    let currentScheduleData = null;
    let hasExportableSchedule = false;
    let serverClockOffset = 0;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

    const showAlert = (message, type = 'danger') => {
        if (scheduleAlert) {
            scheduleAlert.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    };

    async function loadSchedule() {
        try {
            const response = await fetch(`${BASE_URL}app/api/schedule.php`);
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (err) {
                throw new Error('Server mengembalikan respons yang bukan JSON.');
            }

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal memuat data jadwal perkuliahan.');
            }

            const data = result.data;
            currentScheduleData = data;
            serverClockOffset = data.serverNow ? new Date(data.serverNow).getTime() - Date.now() : 0;
            renderSemesterBanner(data.semester);
            renderScheduleCards(data);

        } catch (error) {
            currentScheduleData = null;
            setExportButtonsEnabled(false);
            showAlert(error.message);
            if (dailyScheduleList) {
                dailyScheduleList.innerHTML = `
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-exclamation-circle fs-2 text-danger d-block mb-2"></i>
                        Gagal memuat jadwal perkuliahan.
                    </div>`;
            }
        }
    }

    function renderSemesterBanner(sem) {
        if (!sem) {
            activeSemesterBanner?.classList.add('d-none');
            return;
        }

        activeSemesterBanner?.classList.remove('d-none');
        if (activeSemesterTitle) {
            activeSemesterTitle.textContent = sem.semesterTitle || `Semester ${sem.semesterNumber}`;
        }
        if (activeSemesterInfo) {
            activeSemesterInfo.textContent = `${sem.studyProgram || '-'} (${sem.majorType || '-'}) - Kelas ${sem.classGroup || '-'} | Angkatan ${sem.batchYear || '-'}`;
        }
    }

    function renderScheduleCards(data) {
        if (!dailyScheduleList) return;

        const scheduleByDay = data.scheduleByDay || {};
        const todayDay = data.todayDay || '';
        const tomorrowDay = data.tomorrowDay || '';
        const daysOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        let hasAnyCourse = false;
        daysOrder.forEach(day => {
            if (scheduleByDay[day] && scheduleByDay[day].length > 0) {
                hasAnyCourse = true;
            }
        });

        hasExportableSchedule = hasAnyCourse;
        setExportButtonsEnabled(hasAnyCourse);

        if (!hasAnyCourse) {
            renderExportSchedule(data);
            dailyScheduleList.innerHTML = `
                <div class="col-12 text-center text-muted py-5">
                    <div class="card border-0 shadow-sm rounded-4 p-5">
                        <i class="bi bi-calendar-x fs-1 text-secondary mb-2"></i>
                        <h6 class="fw-semibold text-dark">Belum Ada Jadwal Mata Kuliah</h6>
                        <p class="small text-muted mb-0">${data.semester ? 'Tambahkan mata kuliah pada menu Semester & Matkul untuk menampilkan jadwal.' : 'Tidak ada semester aktif. Aktifkan semester melalui menu Semester & Matkul.'}</p>
                    </div>
                </div>`;
            return;
        }

        let html = '';

        daysOrder.forEach(day => {
            const courses = scheduleByDay[day] || [];
            if (courses.length === 0) return; // Hanya tampilkan hari yang memiliki jadwal

            const isToday = (day === todayDay);
            const isTomorrow = (day === tomorrowDay);

            let dayBadge = '';
            let cardBorder = 'border-top border-4 border-secondary';

            if (isToday) {
                dayBadge = `<span class="badge bg-primary text-white rounded-pill px-3 py-1 shadow-sm"><i class="bi bi-star-fill me-1"></i>HARI INI</span>`;
                cardBorder = 'border-top border-4 border-primary shadow-md';
            } else if (isTomorrow) {
                dayBadge = `<span class="badge bg-success text-white rounded-pill px-3 py-1 shadow-sm"><i class="bi bi-calendar-plus me-1"></i>BESOK</span>`;
                cardBorder = 'border-top border-4 border-success';
            }

            let courseListHtml = '';
            courses.forEach(c => {
                const startTime = c.startTime ? c.startTime.substring(0, 5) : 'TBA';
                const endTime = c.endTime ? c.endTime.substring(0, 5) : 'TBA';
                const timeRange = (startTime !== 'TBA' && endTime !== 'TBA') ? `${startTime} - ${endTime}` : 'Waktu TBA';
                const courseClass = c.courseClass ? `Kelas ${escapeHtml(c.courseClass)}` : 'Kelas TBA';
                const lecturer = c.lecturerName ? escapeHtml(c.lecturerName) : 'Dosen Pengampu TBA';
                const typeBadgeClass = c.courseType === 'Praktek' ? 'bg-warning text-dark' : 'bg-primary text-white';
                const isAttendanceActive  = isToday && isCourseAttendanceActive(c);
                const hasTimePassed       = isToday && hasCourseStartPassed(c);
                const detailUrl           = `${BASE_URL}course-detail?id=${encodeURIComponent(c.courseId)}&from=schedule`;
                const lupaAbsensiUrl      = `${BASE_URL}lupa-absensi?course_id=${encodeURIComponent(c.courseId)}&from=schedule`;

                courseListHtml += `
                    <a href="${detailUrl}" class="schedule-course-item d-block p-3 mb-2 rounded-3 border bg-white hover-shadow transition-all text-decoration-none text-dark">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <span class="badge ${typeBadgeClass} rounded-pill px-2 py-1 small fw-medium">${escapeHtml(c.courseType || 'Teori')}</span>
                            <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small fw-semibold">
                                <i class="bi bi-clock me-1 text-primary"></i>${timeRange}
                            </span>
                        </div>
                        ${isAttendanceActive ? `<span class="badge text-white mb-2" style="cursor:pointer; background-color: #198754; transition: background-color 0.2s ease-in-out; display: inline-block;" onmouseover="this.style.backgroundColor='#15803d';" onmouseout="this.style.backgroundColor='#198754';" onclick="event.preventDefault();event.stopPropagation();window.open('https://akademik.polban.ac.id/','_blank');">Jam Aktif Absensi</span>` : ''}
                        ${!isAttendanceActive && hasTimePassed ? `<span class="badge text-white mb-2" style="cursor:pointer; background-color: #dc3545; transition: background-color 0.2s ease-in-out; display: inline-block;" onmouseover="this.style.backgroundColor='#b91c1c';" onmouseout="this.style.backgroundColor='#dc3545';" onclick="event.preventDefault();event.stopPropagation();window.location.href='${lupaAbsensiUrl}';">Lupa Absensi</span>` : ''}
                        <h6 class="fw-bold text-dark mb-1 fs-6">${escapeHtml(c.courseTitle)}</h6>
                        <small class="text-secondary d-block mb-1">
                            ${escapeHtml(c.courseCode || '-')}
                        </small>
                        <div class="d-flex align-items-center justify-content-between text-muted small pt-2 border-top mt-2">
                            <span><i class="bi bi-geo-alt me-1 text-danger"></i>${courseClass}</span>
                            <span><i class="bi bi-person me-1 text-secondary"></i>${lecturer}</span>
                        </div>
                    </a>`;
            });

            html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 ${cardBorder}">
                        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom border-light">
                            <h5 class="fw-bold text-dark mb-0">${escapeHtml(day)}</h5>
                            ${dayBadge}
                        </div>
                        <div class="card-body p-3 bg-light">
                            ${courseListHtml}
                        </div>
                    </div>
                </div>`;
        });

        dailyScheduleList.innerHTML = html;
        renderExportSchedule(data);
    }

    function setExportButtonsEnabled(enabled) {
        [btnDownloadSchedule, btnExportPdf, btnExportPng].forEach(button => {
            if (button) button.disabled = !enabled;
        });
    }

    function isCourseAttendanceActive(course) {
        if (!course.startTime || !course.endTime) return false;
        const timeParts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23'
        }).format(new Date(Date.now() + serverClockOffset)).split(':').map(Number);
        const currentMinutes = timeParts[0] * 60 + timeParts[1];
        const [startHour, startMinute] = course.startTime.split(':').map(Number);
        const [endHour, endMinute] = course.endTime.split(':').map(Number);
        return currentMinutes >= startHour * 60 + startMinute - 30
            && currentMinutes < endHour * 60 + endMinute;
    }

    function hasCourseStartPassed(course) {
        if (!course.startTime) return false;
        const timeParts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23'
        }).format(new Date(Date.now() + serverClockOffset)).split(':').map(Number);
        const currentMinutes = timeParts[0] * 60 + timeParts[1];
        const [startHour, startMinute] = course.startTime.split(':').map(Number);
        return currentMinutes >= startHour * 60 + startMinute;
    }

    // SVG icons (inline, no external requests; display:block avoids html2canvas flex word-spacing bug)
    const SVG_BOOK = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:4px;flex-shrink:0;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>`;
    const SVG_ROOM = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:4px;flex-shrink:0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>`;

    function renderExportSchedule(data) {
        if (!scheduleExportArea) return;

        const semester  = data.semester || {};
        const daysOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        const dayBlocks = daysOrder.map(day => {
            const courses = data.scheduleByDay?.[day] || [];
            if (!courses.length) return '';

            // NOTE: avoid display:flex inside <td> — html2canvas mis-renders word spacing in flex containers.
            // Use display:block with inline elements instead.
            const rows = courses.map((course, idx) => {
                const range = course.startTime && course.endTime
                    ? `${course.startTime.substring(0, 5)} - ${course.endTime.substring(0, 5)}`
                    : 'Waktu TBA';
                const isTeori    = (course.courseType || 'Teori').toLowerCase() !== 'praktek';
                const badgeBg    = isTeori ? '#1d4ed8' : '#d97706';
                const badgeColor = isTeori ? '#ffffff' : '#ffffff';
                const rowBg      = idx % 2 === 0 ? '#f8fafc' : '#ffffff';

                return `<tr style="background:${rowBg};">
                    <td style="padding:6px 8px; font-size:11px; font-weight:600; color:#0f172a; width:40%; vertical-align:middle; word-spacing:normal; letter-spacing:normal;">
                        ${SVG_BOOK}<span style="vertical-align:middle;">${escapeHtml(course.courseTitle)}</span>
                    </td>
                    <td style="padding:6px 8px; vertical-align:middle; width:12%;">
                        <span style="display:inline-block; background:${badgeBg}; color:${badgeColor}; border-radius:999px; padding:1px 8px; font-size:10px; font-weight:700; white-space:nowrap; word-spacing:normal;">${escapeHtml(course.courseType || 'Teori')}</span>
                    </td>
                    <td style="padding:6px 8px; font-size:10px; color:#0369a1; font-weight:600; vertical-align:middle; width:26%; white-space:nowrap; word-spacing:normal; letter-spacing:normal;">
                        ${escapeHtml(range)}
                    </td>
                    <td style="padding:6px 8px; font-size:10px; color:#374151; vertical-align:middle; width:22%; word-spacing:normal; letter-spacing:normal;">
                        ${SVG_ROOM}<span style="vertical-align:middle;">${escapeHtml(course.courseClass || '-')}</span>
                    </td>
                </tr>`;
            }).join('');

            return `<div style="margin-bottom:10px; border-radius:6px; overflow:hidden; border:1px solid #cbd5e1;">
                <div style="background:#1e3a8a; padding:5px 10px;">
                    <span style="font-size:11px; font-weight:700; color:#ffffff; letter-spacing:0.3px;">${escapeHtml(day)}</span>
                </div>
                <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                    <thead>
                        <tr style="background:#e0e7ff;">
                            <th style="padding:4px 8px; font-size:9px; color:#3730a3; font-weight:700; text-align:left; width:40%; word-spacing:normal;">Mata Kuliah</th>
                            <th style="padding:4px 8px; font-size:9px; color:#3730a3; font-weight:700; text-align:left; width:12%; word-spacing:normal;">Tipe</th>
                            <th style="padding:4px 8px; font-size:9px; color:#3730a3; font-weight:700; text-align:left; width:26%; word-spacing:normal;">Jam</th>
                            <th style="padding:4px 8px; font-size:9px; color:#3730a3; font-weight:700; text-align:left; width:22%; word-spacing:normal;">Ruangan</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        }).join('');

        // Build concise header: "Jadwal Perkuliahan Semester X" + subtitle
        const semNum     = semester.semesterNumber ? `Semester ${escapeHtml(String(semester.semesterNumber))}` : escapeHtml(semester.semesterTitle || 'Jadwal Perkuliahan');
        const majorType  = escapeHtml(semester.majorType || '');
        const classGroup = escapeHtml(semester.classGroup || '-');
        const program    = escapeHtml(semester.studyProgram || '-');
        const batch      = escapeHtml(semester.batchYear || '-');
        const classLabel = majorType ? `${majorType}-${classGroup}` : classGroup;
        const subtitle   = `${classLabel} ${program} ${batch}`;

        scheduleExportArea.innerHTML = `
            <div style="text-align:center; padding:10px 0 8px; border-bottom:2px solid #1e3a8a; margin-bottom:12px;">
                <div style="font-size:14px; font-weight:800; color:#1e3a8a; margin-bottom:3px; word-spacing:normal; letter-spacing:normal;">Jadwal Perkuliahan ${semNum}</div>
                <div style="font-size:10px; color:#64748b; word-spacing:normal; letter-spacing:normal;">${subtitle}</div>
            </div>
            ${dayBlocks || '<p style="font-size:11px; color:#6b7280;">Belum ada jadwal mata kuliah.</p>'}`;
    }

    function exportFilename(extension) {
        const title = activeSemesterTitle?.textContent.trim() || 'Jadwal_Kuliah';
        return `${title.replace(/[^\p{L}\p{N}_-]+/gu, '_')}.${extension}`;
    }

    async function captureExportCanvas() {
        if (document.fonts?.ready) await document.fonts.ready;
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        // scale 2.2: sharp output (~700KB-1.2MB PDF with JPEG 0.82, ~1.2MB PNG)
        return html2canvas(scheduleExportArea, {
            scale: 3.0,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            imageTimeout: 0
        });
    }

    async function setExportBusy(button, busy, label, originalMarkup) {
        if (!button) return;
        button.disabled = busy;
        button.innerHTML = busy
            ? `<span class="spinner-border spinner-border-sm me-1" role="status"></span>${label}`
            : originalMarkup;
    }

    if (btnExportPdf) {
        const originalMarkup = btnExportPdf.innerHTML;
        btnExportPdf.addEventListener('click', async () => {
            if (!currentScheduleData || !hasExportableSchedule || typeof html2canvas === 'undefined' || !window.jspdf?.jsPDF) {
                showAlert('Library ekspor PDF belum tersedia.', 'warning');
                return;
            }
            await setExportBusy(btnExportPdf, true, 'Menyiapkan PDF...', originalMarkup);
            try {
                const canvas    = await captureExportCanvas();
                const { jsPDF } = window.jspdf;
                const margin    = 6;

                const pdf      = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
                const pageW    = pdf.internal.pageSize.getWidth();   // 210mm
                const pageH    = pdf.internal.pageSize.getHeight();  // 297mm
                const contentW = pageW - margin * 2;                 // 198mm
                const contentH = pageH - margin * 2;                 // 285mm

                const imgW       = contentW;
                const totalImgH  = (canvas.height * contentW) / canvas.width;
                const imgData    = canvas.toDataURL('image/jpeg', 0.82);

                let heightLeft = totalImgH;
                let position   = 0;

                pdf.addImage(imgData, 'JPEG', margin, margin, imgW, totalImgH);
                heightLeft -= contentH;

                // Threshold of 2mm prevents creating a blank page due to rounding artifacts
                while (heightLeft > 2) {
                    position += contentH;
                    pdf.addPage();
                    pdf.addImage(imgData, 'JPEG', margin, margin - position, imgW, totalImgH);
                    heightLeft -= contentH;
                }

                pdf.save(exportFilename('pdf'));
            } catch (error) {
                console.error('Schedule PDF export failed:', error);
                showAlert('PDF gagal dibuat.');
            } finally {
                await setExportBusy(btnExportPdf, false, '', originalMarkup);
            }
        });
    }

    if (btnExportPng) {
        const originalMarkup = btnExportPng.innerHTML;
        btnExportPng.addEventListener('click', async () => {
            if (!currentScheduleData || !hasExportableSchedule || typeof html2canvas === 'undefined') {
                showAlert('Library ekspor PNG belum tersedia.', 'warning');
                return;
            }
            await setExportBusy(btnExportPng, true, 'Menyiapkan PNG...', originalMarkup);
            try {
                const canvas  = await captureExportCanvas();
                const link    = document.createElement('a');
                link.download = exportFilename('png');
                link.href     = canvas.toDataURL('image/png');
                link.click();
            } catch (error) {
                console.error('Schedule PNG export failed:', error);
                showAlert('PNG gagal dibuat.');
            } finally {
                await setExportBusy(btnExportPng, false, '', originalMarkup);
            }
        });
    }

    async function checkAttendanceWindow() {
        try {
            await fetch(`${BASE_URL}app/api/schedule.php?action=attendance_check`, { headers: { Accept: 'application/json' } });
        } catch (error) {
            console.error('Attendance notification check failed:', error);
        }
    }

    loadSchedule();
    checkAttendanceWindow();
    window.setInterval(() => {
        loadSchedule();
        checkAttendanceWindow();
    }, 60000);
});
