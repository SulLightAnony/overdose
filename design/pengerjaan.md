# PANDUAN ALUR PENGERJAAN (IMPLEMENTATION ROADMAP): OVERDOSE

Dokumen ini mengatur urutan pengerjaan pengembangan sistem aplikasi **Overdose** dari nol hingga siap di-deploy ke lingkungan *production* (Shared Hosting). Pengerjaan dibagi menjadi 10 Fase yang tersusun secara hierarkis berdasarkan dependensi sistem.

---

## FASE 1: PERSIAPAN ENVIRONMENT & INISIALISASI STRUKTUR PROYEK

**Tujuan:** Menyiapkan lingkungan *development* lokal, struktur folder MVC, serta berkas JavaScript & Bootstrap tanpa memerlukan bundler/kompiler berat.

### Langkah Kerja:
1. **Inisialisasi Direktori Proyek:**
   * Buat struktur direktori utama sesuai dokumen desain:
     ```text
     /overdose
     ├── /app
     │   ├── /config
     │   ├── /controllers
     │   ├── /models
     │   ├── /views
     │   └── /api
     ├── /public
     │   ├── /assets
     │   │   ├── /css
     │   │   ├── /img
     │   │   └── /uploads
     │   └── /js
     │       ├── /modules
     │       ├── router.js
     │       └── app.js
     ├── .htaccess
     └── index.php
     ```

2. **Konfigurasi Routing `.htaccess` & Front Controller:**
   * Buat file `.htaccess` di *root* direktori untuk mengarahkan seluruh lalu lintas URL non-file ke `index.php`.
   * Buat file `index.php` sebagai pendorong routing internal dan penangan *session PHP*.

3. **Penataan JavaScript (Vanilla JS ES6+):**
   * Buat berkas JavaScript utama di `/public/js/app.js` menggunakan fitur ES6+ (Native Modules / Fetch API).
   * Pendekatan ini dipilih agar kode langsung siap dijalankan di browser tanpa perlu proses *build* atau *compile* di server shared hosting.

4. **Inisialisasi Framework UI (Bootstrap 5 & Custom CSS):**
   * Masukkan pustaka Bootstrap 5 (CSS & JS bundle) ke dalam folder `/public/assets/css` dan `/public/assets/js` atau sertakan CDN.
   * Siapkan file `/public/assets/css/style.css` untuk kustomisasi UI responsif (tema emas Primordial/Sepuh, mode gelap/terang, skeleton loader).

---

## FASE 2: PERANCANGAN & MIGRASI DATABASE MYSQL

**Tujuan:** Membuat seluruh struktur tabel relasional dan seeding data awal di MySQL lokal.

### Langkah Kerja:
1. **Konfigurasi Database di Local Server (XAMPP/Laragon):**
   * Buat database baru bernama `overdose_db`.

2. **Eksekusi Script DDL (Data Definition Language):**
   * Jalankan query pembuat tabel secara berurutan sesuai dependensi *Foreign Key*:
     1. `users`
     2. `blacklists`
     3. `semesters`
     4. `courses`
     5. `course_materials`
     6. `tasks`
     7. `task_completions`
     8. `task_comments`
     9. `notifications`

3. **Seeding Data Awal (Data Utama):**
   * Tambahkan akun **Primordial** default:
     * Email: `sulthan.faazaa.tif425@polban.ac.id`
     * Role: `primordial`
   * Masukkan data awal tabel `semesters` (contoh: Semester 1 s/d Semester 8) dengan pasangan kode warna `bg_color`.

---

## FASE 3: CORE BACKEND & KONEKSI DATABASE (PDO SINGLETON)

**Tujuan:** Membangun fondasi komunikasi database dan *helper* API di backend PHP.

### Langkah Kerja:
1. **Membuat Class Database Connection (`/app/config/database.php`):**
   * Menerapkan pattern PDO dengan koneksi *Singleton* agar efisien dalam penggunaan RAM server.
   * Set *error mode* ke `PDO::ERRMODE_EXCEPTION` dan *default fetch mode* ke `PDO::FETCH_ASSOC`.

2. **Membuat Helper Response & Session Controller (`/app/config/helpers.php`):**
   * Buat fungsi pendorong JSON output uniform: `jsonResponse($status, $message, $data)`.
   * Buat mekanisme pengecekan autentikasi session user (`requireAuth()`, `requireRole($roles)`).

3. **Konfigurasi Google OAuth Client:**
   * Buat *Credentials OAuth 2.0* di Google Cloud Console.
   * Masukkan `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` ke file konfigurasi backend.

---

## FASE 4: ALUR AUTENTIKASI, BLACKLIST, & ONBOARDING USER

**Tujuan:** Mengimplementasikan alur masuk pengguna, validasi domain `@polban.ac.id`, penolakan email ter-blacklist, dan pengisian data kelas pertama kali.

### Langkah Kerja:
1. **Backend OAuth & Gatekeeper (`/app/api/auth/`):**
   * **Validasi 1:** Terima *Callback* / ID Token dari Google.
   * **Validasi 2 (Domain Check):** Cek apakah email berakhiran `@polban.ac.id`. Jika tidak, tolak.
   * **Validasi 3 (Blacklist Check):** Cek apakah email ada di tabel `blacklists`. Jika ada, kembalikan error: `[Gagal login. Terdapat masalah dengan akunmu.]`.
   * **Validasi 4 (User Handling):**
     * Jika email baru $\rightarrow$ Simpan ke tabel `users` (dengan `name` & `avatar_url` bawaan Google), set session/cookie, tandai *onboarding_completed = false*.
     * Jika email lama $\rightarrow$ Ambil data user, set session/cookie long-lived (30 hari).

2. **Halaman UI Login (`/app/views/login.php`):**
   * Buat antarmuka Login minimalis dengan Tagline **"Our Sanctuary"** dan tombol tunggal **"Login with Google (@polban.ac.id)"**.
   * Tambahkan *client-side script* (JavaScript) untuk mengecek cookie session saat halaman di-load; jika valid, *auto-redirect* ke Dashboard.

3. **Alur Onboarding (First-Time Login Modal/Page):**
   * Jika user terdeteksi belum mengisi data kelas:
     * Tampilkan antarmuka Onboarding wajib: Pilihan **Jurusan** $\rightarrow$ **Prodi (D3/D4)** $\rightarrow$ **Angkatan** $\rightarrow$ **Kelas (A/B/C/D)**.
     * Nama/Nickname dikunci (*read-only*) menggunakan data dari Google.
     * Simpan data via `POST /api/user/onboarding`.

---

## FASE 5: SHELL UTAMA (SPA CONTROLLER) & LAYOUT RESPONSIF

**Tujuan:** Membangun *layout frame* utama aplikasi (Navbar, Topbar, Content Container) yang berjalan dengan rasa SPA (*Single-Page Application*).

### Langkah Kerja:
1. **Rancang Layout HTML Utuh (`/app/views/layout.php`):**
   * **Navbar Kiri:** Terbuka permanen di Desktop, berbentuk *Offcanvas Hamburger Menu* di Mobile.
   * **Top Bar:** Logo `logo.png`, Ikon Lonceng Notifikasi (dengan *red dot indicator*), dan Avatar Profil Pengguna.
   * **Main Content Area (`<main id="app-content">`):** Wadah dinamis tempat komponen halaman di-render oleh JavaScript.

2. **JavaScript Router & Engine Ajax (`/public/js/router.js` & `/public/js/app.js`):**
   * Buat listener untuk meng-intercept klik navigasi (mencegah *full-page reload*).
   * Menerapkan logika pemanggilan API $\rightarrow$ penerimaan JSON $\rightarrow$ pembuatan DOM secara dinamis.
   * Menerapkan komponen **Skeleton Loader** di area `#app-content` saat data sedang di-fetch.

---

## FASE 6: MODUL DASHBOARD & STATISTIK PENGGUNA

**Tujuan:** Menampilkan *Overview* tugas harian, ringkasan progres personal, dan papan peringkat kontributor.

### Langkah Kerja:
1. **Backend API Dashboard (`/app/api/dashboard.php`):**
   * Ambil *Quote of the Day*.
   * Hitung statistik pribadi: Total Tugas Dikerjakan, Belum Dikerjakan, Terlewat (berdasarkan filter kelas & akun user).
   * Ambil daftar tugas *Pending* terdekat (sorted by `deadline ASC`).
   * Ambil Top 3 Contributor dari tabel `tasks` (grouped by `created_by`).

2. **UI Dashboard Component (`/public/js/modules/dashboard.js`):**
   * Render kartu statistik 3 warna (Hijau, Kuning, Merah).
   * Render *Card List* tugas mendesak lengkap dengan *Checkbox Selesai* serbaguna.
   * Render widget *Top Contributor* lengkap dengan penanda badge role (Emas untuk Primordial/Sepuh).

---

## FASE 7: MODUL AKADEMIK & MATERI KULIAH (SEMESTER, MATKUL, MATERIAL)

**Tujuan:** Navigasi hirarkis dari Semester ke Mata Kuliah, serta manajemen file materi perkuliahan.

### Langkah Kerja:
1. **Modul Semester (`/app/api/semesters.php`):**
   * API Fetch seluruh semester.
   * Fitur CRUD Semester (Khusus Primordial & Sepuh) dengan *Edit-in-Place*.

2. **Modul Mata Kuliah (`/app/api/courses.php`):**
   * API Fetch matkul terfilter otomatis berdasarkan `major`, `program`, `batch`, dan `class_name` dari user yang sedang login.
   * Menampilkan Kode Matkul, Judul, Deskripsi, dan Info Kontak Dosen (Nama, Email, WhatsApp).
   * Fitur Tambah/Edit Matkul *Edit-in-Place* untuk Sepuh/Primordial.

3. **Modul Berbagi Materi Perkuliahan (`/app/api/materials.php`):**
   * Endpoint `POST /api/materials` untuk pengunggahan berkas materi perkuliahan oleh seluruh user (Keroco ke atas).
   * Endpoint `GET /api/materials?course_id=X` untuk menampilkan daftar berkas publik yang dapat diunduh.

---

## FASE 8: MODUL SHARING CENTER TUGAS & INTEGRASI GEMINI AI

**Tujuan:** Fitur inti aplikasi untuk manajemen tugas kolaboratif, lazy loading, interaksi komentar, dan penjelasan berbasis AI.

### Langkah Kerja:
1. **Backend API Tasks (`/app/api/tasks.php`):**
   * Implementasi pagination / *Lazy Loading* (`?page=1&limit=10`).
   * Fitur Tambah/Edit/Hapus Tugas oleh pengguna.
   * Logika penandaan status tugas per-user di tabel `task_completions`.

2. **Integrasi Gemini AI Engine dengan Failover Rotasi (`/app/api/generate_ai.php`):**
   * Buat array berisikan 3 API Key Google Gemini (3.1 Flash Lite).
   * Kirim prompt analisis (membaca judul, deskripsi, dan nama lampiran tugas).
   * Jika cURL mengembalikan respon `429 Too Many Requests`, tangkap *exception* dan otomatis alihkan ke API Key berikutnya.

3. **Modul Interaksi & Privasi Tugas:**
   * **Daftar Mahasiswa Selesai (`/app/api/completions.php`):** Ambil daftar user yang sudah selesai. Jika `is_anonymous == true`, samarkan nama menjadi `"someone?"` dan foto profil menjadi `default.png`.
   * **Komentar Publik (`/app/api/comments.php`):** Fitur diskusi real-time/AJAX di bawah detail tugas.

4. **UI Lazy Loading Tugas (`/public/js/modules/tasks.js`):**
   * Implementasi *Intersection Observer API* pada daftar tugas untuk memicu Fetch halaman berikutnya saat *scroll* mendekati bawah.

---

## FASE 9: MODUL NOTIFIKASI, PRIVASI, & SETTINGS (PROFILE CENTER)

**Tujuan:** Manajemen pemberitahuan sistem, preferensi tampilan, dan kontrol privasi akun.

### Langkah Kerja:
1. **Modul Notifikasi Push & In-App (`/app/api/notifications.php`):**
   * Minta izin *Browser Notification API* saat user pertama kali masuk.
   * Buat cron job / trigger pemicu notifikasi untuk: Tugas Baru, Pengingat H-1 *Deadline*, dan Tugas Terlewat.
   * Saat halaman notifikasi dibuka, jalankan query update `is_read = TRUE` untuk mereset *red dot indicator* di topbar.

2. **Profile Center & Settings UI (`/app/views/settings.php`):**
   * Render informasi Profil (Foto, Nama Google, Badge Level).
   * Implementasi Toggle Switch:
     * **Mode Gelap / Terang:** Simpan preferensi di `localStorage` dan terapkan *class* CSS `.dark-theme` pada `<body>`.
     * **Toggle Notifikasi:** Aktifkan/Matikan ijin notifikasi.
     * **Sembunyikan Identitas (Anonymous Mode):** Update kolom `is_anonymous` di tabel `users`.
   * Tombol Logout & Hapus Akun.

3. **Modul Manajemen Pengguna & Blacklist (Khusus Primordial/Sepuh):**
   * Buat view khusus bagi Primordial untuk mengubah *role* user (Primordial, Sepuh, Keroco).
   * Buat view manajemen Blacklist Email bagi Primordial/Sepuh untuk menambah/menghapus email terblokir.

---

## FASE 10: TESTING, OPTIMASI, & DEPLOYMENT KE SHARED HOSTING

**Tujuan:** Pengujian menyeluruh dan migrasi berkas langsung dari *local environment* ke *live hosting* tanpa langkah kompilasi server.

### Langkah Kerja:
1. **Cross-Device & Responsiveness Testing:**
   * Uji alur UI di layar Desktop, Tablet, dan Smartphone (iOS & Android).
   * Uji fungsionalitas menu samping (Offcanvas) pada perangkat seluler.

2. **Pengujian Fitur Khusus:**
   * Uji penolakan akun non-Polban & email ter-blacklist.
   * Uji rotasi API Gemini AI saat kuota limit tercapai.
   * Uji penyamaran nama `"someone?"` pada log penyelesaian tugas.

3. **Verifikasi Berkas Frontend:**
   * Pastikan seluruh file JavaScript Vanilla berada di `/public/js/` dan di-include dengan tag `<script type="module" src="...">` atau `<script src="...">` standar.

4. **Deployment ke Shared Hosting (cPanel / hPanel):**
   * Export database `overdose_db` dari lokal dan Import ke MySQL Remote Hosting.
   * Upload seluruh struktur folder proyek via FTP/File Manager ke direktori `public_html` atau direktori utama hosting.
   * Sesuaikan file `/app/config/database.php` dengan nama database, username, dan password MySQL hosting.
   * Pastikan file `.htaccess` aktif di server Apache hosting untuk menjamin *Clean URL Routing* berjalan lancar.