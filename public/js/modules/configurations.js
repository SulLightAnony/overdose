/**
 * ====================================================================================
 * MODULE: Frontend JavaScript Module Configurations
 * FILE LOCATION: public/js/modules/configurations.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File modul JS ini bertindak sebagai pengontrol utama seluruh interaksi asinkron (AJAX)
 * dan manipulasi DOM pada Halaman Configurations (app/views/configurations.php).
 * Memproses pemuatan profil pengguna, sinkronisasi toggle switch (Dark Mode, Notifikasi,
 * Mode Anonim), registrasi Service Worker Web Push, aksi Hapus Akun Mandiri, serta
 * pengelolaan tabel dinamis Manajemen Pengguna dan Blacklist Email (Primordial & Sepuh).
 * 
 * RELASI DENGAN FILE LAIN (FILE RELATIONS):
 * - Views Consumer: app/views/configurations.php
 * - Backend API Endpoint: app/api/configurations.php
 * - Service Worker: public/service_worker.js
 * 
 * LOGIKA & ALUR KERJA (HOW IT WORKS):
 * 1. Inisialisasi & Pemuatan Profil (`loadProfile`):
 *    - Mengirim request GET ke `app/api/configurations.php?action=get_profile`.
 *    - Merender nama, email, avatar Google, dan badge peran pengguna.
 *    - Mengatur status checked pada toggle switch `#toggleNotifications` dan `#toggleAnonymous`.
 * 
 * 2. Manajemen Dark Mode (`localStorage`):
 *    - Membaca preferensi tema dari `localStorage.getItem('theme')`.
 *    - Jika bernilai 'dark', centang `#toggleDarkMode` dan tambahkan class `.dark-theme` pada `document.body`.
 *    - Memantau event `change` pada `#toggleDarkMode`: Ubah class `.dark-theme` dan simpan status ke `localStorage`.
 * 
 * 3. Registrasi Service Worker & Izin Push Notification:
 *    - Saat `#toggleNotifications` diaktifkan (checked), panggil `Notification.requestPermission()`.
 *    - Didaftarkan Service Worker `public/service_worker.js` jika belum terdaftar.
 *    - Dapatkan Push Subscription (endpoint, keys) dan kirimkan ke `app/api/notifications.php?action=subscribe_push`.
 * 
 * 4. Update Preferensi Akun:
 *    - Memantau perubahan pada `#toggleNotifications` dan `#toggleAnonymous`.
 *    - Kirim data terbaru via AJAX POST ke `app/api/configurations.php?action=update_preferences`.
 * 
 * 5. Aksi Hapus Akun Mandiri (Hard Delete):
 *    - Memantau klik tombol `#btnConfirmDeleteAccount` pada modal konfirmasi.
 *    - Kirim request DELETE/POST ke `app/api/configurations.php?action=delete_account`.
 *    - Jika berhasil, redirect otomatis pengguna ke halaman login/landing page.
 * 
 * 6. Admin Management Center (Khusus Primordial & Sepuh):
 *    - Pemuatan Tabel Pengguna (`loadUsersTable`):
 *      * Fetch data dari `action=list_users`.
 *      * Render tabel pengguna dengan dropdown ubah peran (Primordial saja) dan tombol blokir/buka blokir.
 *      * Terapkan proteksi visual: Jika pengguna diblokir oleh Primordial (`blockedByRole === 'Primordial'`),
 *        tombol Buka Blokir di-disabled untuk akun Sepuh dengan label "Dikunci Primordial".
 *    - Pemuatan Tabel Blacklist (`loadBlacklistTable`):
 *      * Fetch data dari `action=list_blacklist`.
 *      * Submit form `#formAddBlacklist` ke `action=add_blacklist`.
 *      * Aksi Hapus Blacklist (`action=remove_blacklist`) dengan pengecekan proteksi kuncian hierarki.
 */

document.addEventListener('DOMContentLoaded', function() {

    // ==========================================================================
    // 1. INISIALISASI VARIABEL & ELEMEN DOM
    // ==========================================================================
    const alertContainer = document.getElementById('alert-container');
    
    // Elemen Profil
    const profileSkeleton = document.getElementById('profileSkeleton');
    const profileContent = document.getElementById('profileContent');
    const profileName = document.getElementById('profileName');
    const profileEmail = document.getElementById('profileEmail');
    const profileAvatar = document.getElementById('profileAvatar');
    const profileRoleBadge = document.getElementById('profileRoleBadge');
    
    // Elemen Toggle Preferensi
    const toggleDarkMode = document.getElementById('toggleDarkMode');
    const toggleNotifications = document.getElementById('toggleNotifications');
    const toggleAnonymous = document.getElementById('toggleAnonymous');
    
    // Elemen Area Bahaya
    const btnConfirmDeleteAccount = document.getElementById('btnConfirmDeleteAccount');
    
    // Elemen Admin (Bisa null jika user adalah Keroco)
    const usersTableBody = document.getElementById('usersTableBody');
    const blacklistTableBody = document.getElementById('blacklistTableBody');
    const searchUser = document.getElementById('searchUser');
    const formAddBlacklist = document.getElementById('formAddBlacklist');

    // State Data Admin
    let usersDataList = [];

    // Kunci Publik VAPID (Ganti dengan kunci publik yang digenerate oleh server kamu)
    const PUBLIC_VAPID_KEY = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLcg05SRkqjw';

    // ==========================================================================
    // 2. PEMUATAN PROFIL & PREFERENSI (READ)
    // ==========================================================================
    function loadProfile() {
        fetch(`${BASE_URL}app/api/configurations.php?action=get_profile`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    const p = res.data;
                    
                    // Set Data Profil
                    if (profileName) profileName.textContent = p.userName || '';
                    if (profileEmail) profileEmail.textContent = p.emailAddress || '';
                    if (profileAvatar) profileAvatar.src = p.avatarUrl ? p.avatarUrl : `${BASE_URL}public/assets/img/logo.png`;
                    
                    // Set Badge Role
                    if (profileRoleBadge) {
                        profileRoleBadge.textContent = p.roleLevel || 'Role';
                        if (p.roleLevel === 'Primordial') profileRoleBadge.className = 'badge bg-warning text-dark fs-6';
                        else if (p.roleLevel === 'Sepuh') profileRoleBadge.className = 'badge bg-info text-dark fs-6';
                        else profileRoleBadge.className = 'badge bg-secondary fs-6';
                    }

                    // Set Toggle Status
                    if (toggleAnonymous) toggleAnonymous.checked = (parseInt(p.isAnonymous) === 1);
                    if (toggleNotifications) toggleNotifications.checked = (parseInt(p.enableNotifications) === 1);

                    // Sembunyikan skeleton, tampilkan konten
                    if (profileSkeleton) profileSkeleton.classList.add('d-none');
                    if (profileContent) profileContent.classList.remove('d-none');
                } else {
                    showAlert('Gagal memuat profil: ' + (res.message || 'Error'), 'danger');
                }
            })
            .catch(err => {
                console.error('Error fetching profile:', err);
                showAlert('Terjadi kesalahan koneksi saat memuat profil.', 'danger');
            });
    }

    // ==========================================================================
    // 3. PENGELOLAAN DARK MODE (LOCALSTORAGE)
    // ==========================================================================
    function initDarkMode() {
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') {
            document.body.classList.add('dark-theme');
            if (toggleDarkMode) toggleDarkMode.checked = true;
        }

        if (toggleDarkMode) {
            toggleDarkMode.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    localStorage.setItem('theme', 'light');
                }
            });
        }
    }

    // ==========================================================================
    // 4. PEMBARUAN PREFERENSI & PUSH SUBSCRIPTION
    // ==========================================================================
    function updatePreferences() {
        const isAnon = toggleAnonymous && toggleAnonymous.checked ? 1 : 0;
        const isNotif = toggleNotifications && toggleNotifications.checked ? 1 : 0;

        let formData = new FormData();
        formData.append('action', 'update_preferences');
        formData.append('isAnonymous', isAnon);
        formData.append('enableNotifications', isNotif);

        fetch(`${BASE_URL}app/api/configurations.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (!res.success) {
                showAlert('Gagal menyimpan preferensi: ' + res.message, 'warning');
            }
        })
        .catch(err => console.error('Preference Update Error:', err));
    }

    // Event Listener untuk Toggle DB
    if (toggleAnonymous) {
        toggleAnonymous.addEventListener('change', updatePreferences);
    }
    if (toggleNotifications) {
        toggleNotifications.addEventListener('change', function() {
            updatePreferences();
            if (this.checked) {
                handlePushSubscription();
            }
        });
    }

    // Registrasi Service Worker & Izin Notifikasi
    function handlePushSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            showAlert('Browser Anda tidak mendukung push notification latar belakang.', 'warning');
            return;
        }

        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                navigator.serviceWorker.register(`${BASE_URL}public/service_worker.js`)
                .then(function(registration) {
                    const applicationServerKey = urlB64ToUint8Array(PUBLIC_VAPID_KEY);
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: applicationServerKey
                    });
                })
                .then(function(subscription) {
                    const subData = JSON.parse(JSON.stringify(subscription));
                    
                    let payload = {
                        action: 'subscribe_push',
                        endpoint: subData.endpoint,
                        p256dhKey: subData.keys.p256dh,
                        authToken: subData.keys.auth
                    };

                    fetch(`${BASE_URL}app/api/notifications.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                })
                .catch(function(err) {
                    console.error('Failed to subscribe to push service:', err);
                });
            } else {
                if (toggleNotifications) toggleNotifications.checked = false;
                updatePreferences();
                showAlert('Izin notifikasi ditolak oleh browser.', 'warning');
            }
        });
    }

    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // ==========================================================================
    // 5. HAPUS AKUN MANDIRI (HARD DELETE)
    // ==========================================================================
    if (btnConfirmDeleteAccount) {
        btnConfirmDeleteAccount.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = 'Menghapus...';

            fetch(`${BASE_URL}app/api/configurations.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_account' })
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    window.location.href = BASE_URL; // Redirect to login
                } else {
                    showAlert('Gagal menghapus akun: ' + res.message, 'danger');
                    this.disabled = false;
                    this.textContent = 'Ya, Hapus Akun Saya';
                }
            })
            .catch(err => {
                console.error('Delete Account Error:', err);
                showAlert('Terjadi kesalahan server.', 'danger');
                this.disabled = false;
                this.textContent = 'Ya, Hapus Akun Saya';
            });
        });
    }

    // ==========================================================================
    // 6. ADMIN CENTER: MANAJEMEN PENGGUNA & BLACKLIST
    // ==========================================================================
    if ((CURRENT_USER_ROLE === 'Primordial' || CURRENT_USER_ROLE === 'Sepuh') && usersTableBody && blacklistTableBody) {
        
        // --- Tab 1: Manajemen Pengguna ---
        function loadUsersTable() {
            fetch(`${BASE_URL}app/api/configurations.php?action=list_users`)
                .then(response => response.json())
                .then(res => {
                    if (res.success && res.data) {
                        usersDataList = res.data.users;
                        renderUsersTable(usersDataList);
                    }
                })
                .catch(err => console.error('Error fetching users:', err));
        }

        function renderUsersTable(users) {
            if (!usersTableBody) return;
            usersTableBody.innerHTML = '';

            if (users.length === 0) {
                usersTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Tidak ada pengguna ditemukan.</td></tr>`;
                return;
            }

            users.forEach(u => {
                const isLockedByPrimordial = (u.blockedByRole === 'Primordial' && CURRENT_USER_ROLE === 'Sepuh');
                
                // Role Dropdown (Only Primordial can change roles)
                let roleHtml = `<span class="badge bg-secondary">${escapeHtml(u.roleLevel)}</span>`;
                if (CURRENT_USER_ROLE === 'Primordial') {
                    roleHtml = `
                        <select class="form-select form-select-sm role-select" data-id="${u.userId}" style="width: 120px;">
                            <option value="Keroco" ${u.roleLevel === 'Keroco' ? 'selected' : ''}>Keroco</option>
                            <option value="Sepuh" ${u.roleLevel === 'Sepuh' ? 'selected' : ''}>Sepuh</option>
                            <option value="Primordial" ${u.roleLevel === 'Primordial' ? 'selected' : ''}>Primordial</option>
                        </select>
                    `;
                }

                // Action Buttons
                let actionHtml = '';
                let statusHtml = `<span class="badge bg-success">Aktif</span>`;
                
                if (u.userStatus === 'blocked') {
                    statusHtml = `<span class="badge bg-danger">Diblokir</span> <small class="d-block text-muted" style="font-size:0.65rem;">Oleh ${escapeHtml(u.blockedByRole)}</small>`;
                    if (isLockedByPrimordial) {
                        actionHtml = `<button class="btn btn-sm btn-outline-secondary" disabled>Dikunci Primordial</button>`;
                    } else {
                        actionHtml = `<button class="btn btn-sm btn-success btn-toggle-block" data-id="${u.userId}" data-status="active">Buka Blokir</button>`;
                    }
                } else {
                    actionHtml = `<button class="btn btn-sm btn-danger btn-toggle-block" data-id="${u.userId}" data-status="blocked">Blokir</button>`;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="fw-bold">${escapeHtml(u.userName)}</div>
                        <small class="text-muted">${escapeHtml(u.emailAddress)}</small>
                    </td>
                    <td>${roleHtml}</td>
                    <td>${statusHtml}</td>
                    <td class="text-end">${actionHtml}</td>
                `;
                usersTableBody.appendChild(tr);
            });
        }

        // Search User Logic
        if (searchUser) {
            searchUser.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const filtered = usersDataList.filter(u => 
                    u.userName.toLowerCase().includes(query) || 
                    u.emailAddress.toLowerCase().includes(query)
                );
                renderUsersTable(filtered);
            });
        }

        // Event Delegation for User Actions
        if (usersTableBody) {
            usersTableBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('role-select')) {
                    const userId = e.target.getAttribute('data-id');
                    const newRole = e.target.value;
                    updateUserRole(userId, newRole);
                }
            });

            usersTableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-toggle-block')) {
                    const userId = e.target.getAttribute('data-id');
                    const newStatus = e.target.getAttribute('data-status');
                    toggleUserBlock(userId, newStatus);
                }
            });
        }

        function updateUserRole(targetId, newRole) {
            fetch(`${BASE_URL}app/api/configurations.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_user_role', targetUserId: targetId, newRole: newRole })
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) loadUsersTable();
                else { showAlert(res.message, 'danger'); loadUsersTable(); }
            })
            .catch(err => console.error(err));
        }

        function toggleUserBlock(targetId, newStatus) {
            fetch(`${BASE_URL}app/api/configurations.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_user_block', targetUserId: targetId, newStatus: newStatus })
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) loadUsersTable();
                else showAlert(res.message, 'danger');
            })
            .catch(err => console.error(err));
        }

        // --- Tab 2: Blacklist Email ---
        function loadBlacklistTable() {
            fetch(`${BASE_URL}app/api/configurations.php?action=list_blacklist`)
                .then(response => response.json())
                .then(res => {
                    if (res.success && res.data) {
                        renderBlacklistTable(res.data.blacklist);
                    }
                })
                .catch(err => console.error('Error fetching blacklist:', err));
        }

        function renderBlacklistTable(blacklist) {
            if (!blacklistTableBody) return;
            blacklistTableBody.innerHTML = '';

            if (blacklist.length === 0) {
                blacklistTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Tidak ada email terblokir.</td></tr>`;
                return;
            }

            blacklist.forEach(b => {
                const isLockedByPrimordial = (b.blockedByRole === 'Primordial' && CURRENT_USER_ROLE === 'Sepuh');
                
                let actionHtml = '';
                if (isLockedByPrimordial) {
                    actionHtml = `<button class="btn btn-sm btn-outline-secondary" disabled>Dikunci Primordial</button>`;
                } else {
                    actionHtml = `<button class="btn btn-sm btn-outline-danger btn-remove-blacklist" data-id="${b.blacklistId}">Hapus</button>`;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-bold">${escapeHtml(b.emailAddress)}</td>
                    <td><small>${escapeHtml(b.reasonDescription || '-')}</small></td>
                    <td>
                        <span class="d-block">${escapeHtml(b.addedByName || 'Sistem')}</span>
                        <span class="badge bg-secondary" style="font-size:0.65rem;">Role: ${escapeHtml(b.blockedByRole)}</span>
                    </td>
                    <td class="text-end">${actionHtml}</td>
                `;
                blacklistTableBody.appendChild(tr);
            });
        }

        if (formAddBlacklist) {
            formAddBlacklist.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSubmitBlacklist');
                if (btn) btn.disabled = true;

                let formData = new FormData(this);
                fetch(`${BASE_URL}app/api/configurations.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        const modalEl = document.getElementById('addBlacklistModal');
                        if (modalEl) {
                            const modalInst = bootstrap.Modal.getInstance(modalEl);
                            if (modalInst) modalInst.hide();
                        }
                        this.reset();
                        loadBlacklistTable();
                    } else {
                        showAlert(res.message, 'danger');
                    }
                })
                .catch(err => console.error(err))
                .finally(() => { if (btn) btn.disabled = false; });
            });
        }

        if (blacklistTableBody) {
            blacklistTableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-remove-blacklist')) {
                    const bId = e.target.getAttribute('data-id');
                    removeBlacklist(bId);
                }
            });
        }

        function removeBlacklist(bId) {
            if (!confirm('Hapus email ini dari blacklist?')) return;
            fetch(`${BASE_URL}app/api/configurations.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_blacklist', blacklistId: bId })
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) loadBlacklistTable();
                else showAlert(res.message, 'danger');
            })
            .catch(err => console.error(err));
        }

        // Muat tabel admin saat tab diklik
        const usersTabEl = document.getElementById('users-tab');
        if (usersTabEl) usersTabEl.addEventListener('shown.bs.tab', loadUsersTable);

        const blacklistTabEl = document.getElementById('blacklist-tab');
        if (blacklistTabEl) blacklistTabEl.addEventListener('shown.bs.tab', loadBlacklistTable);
        
        // Pemuatan awal jika peran adalah admin
        if (usersTableBody) loadUsersTable();
    }

    // ==========================================================================
    // UTILS
    // ==========================================================================
    function showAlert(msg, type = 'info') {
        if (!alertContainer) return;
        alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
            ${escapeHtml(msg)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
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

    // Eksekusi Pemuatan Awal
    initDarkMode();
    loadProfile();

});