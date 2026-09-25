/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Notifications
 * FILE LOCATION: public/js/modules/notifications.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini bertanggung jawab mengelola seluruh interaksi sistem notifikasi di sisi client/UI.
 * Meliputi pembaruan indikator angka/titik merah (Red Dot) secara otomatis pada topbar/navbar,
 * pemanggilan pemicu otomatis "Lazy Background Check" untuk tenggat waktu tugas (H-1 & Overdue),
 * pemuatan daftar notifikasi in-app ke dalam dropdown/modal, serta pengiriman status baca (isRead).
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Topbar / Layout Navbar: app/views/layout.php (Elemen #notification-badge, #notification-dropdown)
 * - Backend API Endpoint: app/api/notifications.php
 * - Service Worker Register: public/service_worker.js
 * 
 * LOGIKA & ALUR KERJA (HOW IT WORKS):
 * 1. Pemicu Otomatis saat Halaman Dimuat (DOMContentLoaded):
 *    - Panggil fungsi `checkUnreadCount()` untuk memperbarui indikator Red Dot di topbar.
 *    - Panggil fungsi `triggerLazyDeadlineCheck()` secara asinkron di background untuk memicu
 *      pengecekan otomatis tugas mendekati deadline (H-1) atau terlewat tanpa mengganggu loading UI utama.
 * 
 * 2. Perhitungan & Rendering Indikator Red Dot (`checkUnreadCount`):
 *    - Mengirim request GET ke `app/api/notifications.php?action=unread_count`.
 *    - Jika `unreadCount > 0`, tampilkan elemen badge/red dot pada ikon notifikasi topbar.
 *    - Jika `unreadCount === 0`, sembunyikan elemen badge/red dot.
 * 
 * 3. Lazy Background Check Trigger (`triggerLazyDeadlineCheck`):
 *    - Mengirim request GET ke `app/api/notifications.php?action=lazy_check_deadlines`.
 *    - Jika ada notifikasi pengingat H-1 atau Overdue baru yang ter-generate, perbarui ulang angka `checkUnreadCount()`.
 * 
 * 4. Pemuatan Daftar Notifikasi (`loadNotificationList`):
 *    - Dipanggil saat pengguna mengklik ikon notifikasi/dropdown topbar.
 *    - Mengirim request GET ke `app/api/notifications.php?action=list&page=1`.
 *    - Merender daftar item notifikasi (Judul, Pesan, Tipe, Timestamp, dan Link Target).
 * 
 * 5. Tandai Dibaca / Reset Red Dot (`markNotificationsAsRead`):
 *    - Dipanggil saat dropdown/menu notifikasi dibuka atau saat tombol "Tandai Semua Dibaca" diklik.
 *    - Mengirim request POST ke `app/api/notifications.php?action=mark_as_read`.
 *    - Mereset indikator Red Dot di topbar menjadi hilang/nol.
 * 
 * ATURAN PENULISAN KODE:
 * - Tidak boleh menggunakan emoji di dalam penulisan kodenya.
 */

document.addEventListener('DOMContentLoaded', function() {

    // ==========================================================================
    // 1. INISIALISASI ELEMEN DOM
    // ==========================================================================
    const notificationBadge = document.getElementById('notificationBadge'); // Red dot / angka badge
    const notificationListContainer = document.getElementById('notificationListContainer'); // Wadah list notifikasi
    const notificationDropdownBtn = document.getElementById('notificationDropdownBtn'); // Tombol bell di navbar
    const btnMarkAllRead = document.getElementById('btnMarkAllRead');

    // ==========================================================================
    // 2. PEMICU LAZY BACKGROUND CHECK (H-1 & OVERDUE)
    // ==========================================================================
    function triggerLazyDeadlineCheck() {
        fetch(`${BASE_URL}app/api/notifications.php?action=lazy_check_deadlines`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data && res.data.newNotificationsGenerated > 0) {
                    // Jika ada notifikasi baru ter-generate, perbarui angka badge
                    checkUnreadCount();
                }
            })
            .catch(err => console.error('Lazy Deadline Check Error:', err));
    }

    // ==========================================================================
    // 3. PERHITUNGAN UNREAD COUNT (RED DOT)
    // ==========================================================================
    function checkUnreadCount() {
        if (!notificationBadge) return;

        fetch(`${BASE_URL}app/api/notifications.php?action=unread_count`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    const count = parseInt(res.data.unreadCount);
                    if (count > 0) {
                        notificationBadge.textContent = count > 99 ? '99+' : count;
                        notificationBadge.classList.remove('d-none');
                    } else {
                        notificationBadge.classList.add('d-none');
                    }
                }
            })
            .catch(err => console.error('Unread Count Error:', err));
    }

    // ==========================================================================
    // 4. PEMUATAN DAFTAR NOTIFIKASI
    // ==========================================================================
    function loadNotificationList() {
        if (!notificationListContainer) return;
        
        notificationListContainer.innerHTML = `<div class="text-center p-3 text-muted">Memuat notifikasi...</div>`;

        fetch(`${BASE_URL}app/api/notifications.php?action=list&page=1&limit=15`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    renderNotificationList(res.data.notifications);
                } else {
                    notificationListContainer.innerHTML = `<div class="text-center p-3 text-danger">Gagal memuat notifikasi.</div>`;
                }
            })
            .catch(err => {
                console.error('Load Notifications Error:', err);
                notificationListContainer.innerHTML = `<div class="text-center p-3 text-danger">Terjadi kesalahan koneksi.</div>`;
            });
    }

    function renderNotificationList(notifications) {
        if (notifications.length === 0) {
            notificationListContainer.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                    Belum ada notifikasi baru.
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(n => {
            const isUnread = parseInt(n.isRead) === 0;
            const bgClass = isUnread ? 'bg-light border-start border-4 border-primary' : '';
            const fwClass = isUnread ? 'fw-bold' : '';
            
            // Penentuan Ikon Berdasarkan Tipe
            let iconClass = 'bi-info-circle text-primary';
            if (n.notificationType === 'reminder_h1') iconClass = 'bi-clock-history text-warning';
            else if (n.notificationType === 'overdue') iconClass = 'bi-exclamation-octagon text-danger';
            else if (n.notificationType === 'new_task') iconClass = 'bi-journal-plus text-success';

            // Penentuan Tautan
            const targetUrl = n.targetUrl ? n.targetUrl : '#';
            const linkTagStart = n.targetUrl ? `<a href="${escapeHtml(targetUrl)}" class="text-decoration-none text-dark d-block">` : `<div class="d-block">`;
            const linkTagEnd = n.targetUrl ? `</a>` : `</div>`;

            html += `
                <div class="dropdown-item p-3 border-bottom text-wrap ${bgClass}">
                    ${linkTagStart}
                        <div class="d-flex align-items-start">
                            <i class="bi ${iconClass} fs-4 me-3 mt-1"></i>
                            <div>
                                <div class="mb-1 ${fwClass}">${escapeHtml(n.notificationTitle)}</div>
                                <div class="small text-muted mb-1" style="font-size: 0.8rem; line-height: 1.2;">
                                    ${escapeHtml(n.notificationMessage)}
                                </div>
                                <small class="text-secondary" style="font-size: 0.7rem;">
                                    ${formatDate(n.createdAt)}
                                </small>
                            </div>
                        </div>
                    ${linkTagEnd}
                </div>
            `;
        });

        notificationListContainer.innerHTML = html;
    }

    // ==========================================================================
    // 5. TANDAI DIBACA & RESET RED DOT
    // ==========================================================================
    function markNotificationsAsRead(notificationId = 'all') {
        let formData = new FormData();
        formData.append('action', 'mark_as_read');
        formData.append('notificationId', notificationId);

        fetch(`${BASE_URL}app/api/notifications.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Sembunyikan Red Dot di topbar karena semua sudah dibaca
                if (notificationBadge) notificationBadge.classList.add('d-none');
            }
        })
        .catch(err => console.error('Mark as Read Error:', err));
    }

    // ==========================================================================
    // 6. EVENT LISTENERS
    // ==========================================================================
    
    // Saat dropdown notifikasi (bell) dibuka
    if (notificationDropdownBtn) {
        notificationDropdownBtn.addEventListener('show.bs.dropdown', function () {
            loadNotificationList();
            markNotificationsAsRead('all');
        });
    }

    // Tombol manual tandai semua dibaca (opsional, jika ada di UI)
    if (btnMarkAllRead) {
        btnMarkAllRead.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markNotificationsAsRead('all');
            loadNotificationList(); // Refresh list agar background light unread hilang
        });
    }

    // ==========================================================================
    // 7. FUNGSI UTILITAS
    // ==========================================================================
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
        const options = { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString('id-ID', options);
    }

    // ==========================================================================
    // 8. EKSEKUSI SAAT INIT
    // ==========================================================================
    triggerLazyDeadlineCheck();
    checkUnreadCount();

});