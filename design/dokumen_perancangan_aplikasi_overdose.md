# DOKUMEN PERANCANGAN SISTEM: OVERDOSE

**"Our Sanctuary" - Task Management & Sharing Platform for Polban Students**

## I. GAMBARAN UMUM

**Overdose** adalah aplikasi web manajemen tugas kolaboratif yang didesain eksklusif untuk sivitas akademika Politeknik Negeri Bandung (Polban). Aplikasi ini memungkinkan mahasiswa untuk saling berbagi tugas, berdiskusi, dan melacak progres penyelesaian tugas secara real-time dengan bantuan AI untuk penjelasan tugas.

### 1.1. Teknologi yang Digunakan

* **Frontend:** HTML5, CSS3 (Bootstrap + Native untuk custom UI responsif), JavaScript (Vanilla JS / ES6+ Modular untuk manipulasi DOM dan Fetch API yang ringan tanpa butuh kompilasi *build step*).

* **Backend:** PHP (Native terstruktur dengan konsep MVC).

* **Database:** MySQL.

* **Arsitektur UI:** *Single-Page Application (SPA) Feel* (Menggunakan AJAX/Fetch agar pergantian data antar halaman tidak memerlukan *full-page reload* setelah *load* awal).

* **AI Engine:** Google Gemini 3.1 Flash Lite (via REST API dengan sistem *fallback*).

### 1.2. Daftar Menu & Fungsi Singkat

1. **Dashboard:** Halaman utama berisi daftar tugas mendesak, statistik penyelesaian, top kontributor, dan *Quote of the Day*.

2. **Akademik (Semester & Matkul):** Navigasi hierarki dari Semester -> Mata Kuliah -> Daftar Tugas.

3. **Notifikasi:** Pusat pemberitahuan tugas baru, pengingat H-1 *deadline*, dan tugas terlewat.

4. **Pengaturan (Settings):** Manajemen profil, preferensi tema (Light/Dark), toggle notifikasi, dan pengaturan privasi (*Anonymous mode*).

5. **Manajemen Pengguna (Khusus Primordial):** Mengatur level *user* (Primordial, Sepuh, Keroco).

6. **Blacklist (Khusus Primordial/Sepuh):** Mendaftarkan email yang diblokir dari aplikasi (seperti email dosen/mata-mata).

## II. ARSITEKTUR & SISTEM OTORISASI

### 2.1. Sistem Level Pengguna (Role-Based Access)

Tidak ada sistem *level-up* otomatis. Role diatur murni oleh Primordial.

* **Primordial (Owner):** Akun absolut (`sulthan.faazaa.tif425@polban.ac.id`). Bisa mengatur level user, mengedit/menghapus segala entitas, menambah blacklist, dll.

  * *Visual UI:* Nama dan avatar dibingkai kotak berwarna Emas.

* **Sepuh (Admin):** Bisa menambah/mengedit entitas struktural (Jurusan, Prodi, Kelas, Semester, Matkul, Tugas) dan Blacklist.

  * *Visual UI:* Teks nama berwarna Emas.

* **Keroco (Member):** Hanya bisa CRUD tugas (karena sifatnya *sharing center*), memberi komentar, dan menandai tugas selesai.

  * *Visual UI:* Teks nama standar/biasa.

### 2.2. Alur Login & Onboarding

1. **Halaman Login:** User langsung diarahkan ke `/login`. Hanya terdapat satu tombol: **"Login with Google (@polban.ac.id)"** dan tagline *"Our Sanctuary"*.

2. **Validasi Backend:**

   * Jika domain bukan `@polban.ac.id`, tolak.

   * Cek tabel `blacklists`. Jika ada, tolak dengan pesan: `[Gagal login. Terdapat masalah dengan akunmu.]` tanpa penjelasan lebih lanjut.

3. **Onboarding (First Time Login):**

   * User wajib memilih: Jurusan -> Prodi (D4/D3) -> Angkatan -> Kelas.

   * Nickname dikunci menggunakan nama dari akun Google (menghindari spam anonim bernama aneh).

4. **Session & Cookie:** Menggunakan cookie berumur panjang (misal 30 hari) untuk *Remember Me*. Jika session habis, auto-check token Google di latar belakang agar user langsung masuk ke Dashboard tanpa menekan tombol lagi.

## III. DETAIL ANTARMUKA (UI/UX) & FUNGSIONALITAS

### 3.1. Layout Utama (Global)

* **Navbar (Samping/Kiri):** Selalu muncul di PC, berupa *offcanvas/hamburger menu* di Mobile. Berisi link ke Dashboard, Akademik, dan ikon gerigi (Settings) di bagian paling bawah.

* **Top Bar (Atas):**

  * Kiri: Logo/Teks "Overdose" (sementara menggunakan `logo.png` kosong).

  * Kanan: Ikon Lonceng Notifikasi (dengan *red dot* dan angka untuk *unread*) dan Foto Profil User (jika diklik masuk ke *Profile Center* di dalam Settings).

* **Edit-in-Place:** Tidak ada halaman khusus Admin Panel. Tombol Edit/Delete (ikon pensil/tempat sampah) akan muncul tepat di sebelah elemen (Matkul, Tugas, Semester) apabila user yang login berstatus Primordial atau Sepuh.

### 3.2. Dashboard

* **Header:** Ucapan selamat datang (berdasarkan waktu) dan kalimat penyemangat harian (*Quote of the day*).

* **Statistik Pribadi:** Tiga kartu berjejer: Tugas Dikerjakan, Tugas Belum, Tugas Terlewat (Warna Hijau, Kuning, Merah).

* **Filter Cepat:** Dropdown pilihan Semester.

* **Daftar Tugas Belum Selesai:** Diurutkan dari *deadline* terdekat. Berupa *Card list* ringkas. Ada *checkbox* besar di tiap card untuk mark "Selesai".

* **Top Contributor:** *Leaderboard* kecil menampilkan Top 3 user pengunggah tugas terbanyak (menampilkan badge/warna role).

### 3.3. Halaman Akademik (Semester & Mata Kuliah)

* **Pemilihan Semester:** *Div full-width* berwarna cerah (warna bisa di-set pengurus). Klik untuk masuk ke matkul.

* **Pemilihan Matkul:** Filtered otomatis berdasarkan Kelas, Prodi, Angkatan user. Menampilkan:

  * Kode & Nama Matkul.

  * Deskripsi singkat.

  * Info Dosen (Nama, Email, No. WA).

  * *Tombol Tambah Matkul* (Hanya terlihat oleh Sepuh/Primordial).

### 3.4. Halaman Tugas (Sharing Center) & Detail Tugas

* **Daftar Tugas:**

  * Semua user (*Keroco* ke atas) bisa menambah tugas.

  * Menggunakan *Lazy Loading* (memuat 10 tugas, *scroll* ke bawah untuk memuat sisanya).

  * Card menampilkan: Judul, Deskripsi dipotong, Deadline, Status (Tersedia/Selesai/Telat).

* **Detail Tugas:**

  * **Info Utama:** Deskripsi penuh, Lampiran File (bisa diklik/diunduh).

  * **Penjelasan AI:** Tombol "Jelaskan dengan AI". Saat diklik, muncul *loading animation*, lalu teks penjelasan dari Gemini AI ter-render. AI membaca judul, deskripsi, dan nama file.

  * **Tombol "Tandai Selesai"**.

  * **Daftar "Mahasiswa yang telah selesai":** Menampilkan avatar, nama, badge level, dan jam penyelesaian.

  * **Anonymous Note:** Terdapat teks di bawah daftar: *"Tidak ingin namamu muncul di sini? Atur melalui \[pengaturan\]."*

  * **Komentar Publik:** Kolom diskusi antar user terkait tugas tersebut.

  * Ada informasi juga siapa orang yang membuat tugas ini.

* **Bagian materi:**

  * Semua user bisa melampirkan file untuk setiap matkul dan ini bersifat publik. Jenis file bisa berupa apapun. Dan ada juga tulisan dilampirkan oleh siapa. Bisa dilihat ataupun didownload. Suruh lihat pakai aplikasi eksternal aja. Buat tabel baru untuk bagian ini, karena ini baru aku tambahkan dan tabelnya belum terdaftar di daftar tabel di bawah. Ini tuh juga pake filter kelas, prodi, jurusan, dll. Jadi, setiap kelas/prodi/jurusan/angkatan yang berbeda ibarat memiliki “room” masing-masing.

### 3.5. Halaman Notifikasi

* Berisi daftar pemberitahuan (H-1 Deadline, Tugas Baru di Kelas, Tugas Terlewat).

* Begitu halaman ini dibuka (via AJAX), API backend otomatis meng-update status notifikasi menjadi `is_read = true`, sehingga *red dot* di Top Bar menghilang.

### 3.6. Halaman Settings & Profile Center

* **Profile Center:** Foto, Nama Asli (Google), Badge Level. Tombol: "Logout", "Hapus Akun".

* **Preferensi:**

  * Toggle Mode Terang/Gelap (disimpan di LocalStorage/Cookie).

  * Toggle Notifikasi.

* **Privasi:** Toggle "Sembunyikan Identitas Penyelesaian Tugas". Jika aktif (ON), di daftar penyelesaian tugas orang lain akan melihat avatar default abu-abu, nama "someone?", tapi jam penyelesaian tetap ada.

## IV. PERANCANGAN DATABASE (MySQL)

Berikut adalah struktur tabel yang dibutuhkan. Semua relasi menggunakan *Foreign Key*.

**1. `users`**

* `id` (PK, INT)

* `google_id` (VARCHAR)

* `email` (VARCHAR, harus @polban.ac.id)

* `name` (VARCHAR)

* `avatar_url` (VARCHAR)

* `role` (ENUM: 'primordial', 'sepuh', 'keroco')

* `program` (ENUM: 'D3', 'D4')

* `major` (VARCHAR) - *Contoh: Teknik Komputer dan Informatika*

* `batch` (INT) - *Contoh: 2023*

* `class_name` (VARCHAR) - *Contoh: A, B, C*

* `is_anonymous` (BOOLEAN) - *Untuk privasi tugas*

* `created_at` (TIMESTAMP)

**2. `blacklists`**

* `id` (PK, INT)

* `email` (VARCHAR)

* `added_by` (INT, FK -> users.id)

**3. `semesters`**

* `id` (PK, INT)

* `name` (VARCHAR) - *Contoh: Semester 1, Semester 2*

* `bg_color` (VARCHAR) - *Untuk warna div di UI*

**4. `courses` (Mata Kuliah)**

* `id` (PK, INT)

* `semester_id` (INT, FK -> semesters.id)

* `program`, `major`, `batch`, `class_name` (Untuk *filtering* kepemilikan kelas)

* `code` (VARCHAR)

* `title` (VARCHAR)

* `description` (TEXT)

* `lecturer_name` (VARCHAR)

* `lecturer_email` (VARCHAR)

* `lecturer_wa` (VARCHAR)

**5. `tasks` (Tugas)**

* `id` (PK, INT)

* `course_id` (INT, FK -> courses.id)

* `created_by` (INT, FK -> users.id) - *Untuk sistem poin/top contributor*

* `title` (VARCHAR)

* `description` (TEXT)

* `attachment_url` (VARCHAR, Nullable)

* `deadline` (DATETIME)

* `created_at` (TIMESTAMP)

**6. `task_completions` (Log Penyelesaian Tugas)**

* `id` (PK, INT)

* `task_id` (INT, FK -> tasks.id)

* `user_id` (INT, FK -> users.id)

* `completed_at` (TIMESTAMP)

**7. `task_comments`**

* `id` (PK, INT)

* `task_id` (INT, FK -> tasks.id)

* `user_id` (INT, FK -> users.id)

* `comment` (TEXT)

* `created_at` (TIMESTAMP)

**8. `notifications`**

* `id` (PK, INT)

* `user_id` (INT, FK -> users.id)

* `type` (ENUM: 'new_task', 'deadline_warning', 'missed_task')

* `reference_id` (INT) - *ID dari entitas terkait, misal task_id*

* `message` (VARCHAR)

* `is_read` (BOOLEAN, default FALSE)

* `created_at` (TIMESTAMP)

## V. DETAIL TEKNIS & SPESIFIKASI KHUSUS

### 5.1. Struktur Folder (MVC Pattern - Ringan)

```text
/overdose
├── /app
│   ├── /controllers    # Logika backend PHP
│   ├── /models         # Query PDO Database 
│   ├── /views          # File HTML (dirender oleh PHP)
│   └── /api            # Endpoint JSON untuk Fetch JS
├── /public
│   ├── /assets         # CSS Bootstrap custom, logo.png
│   └── /js             # Script JavaScript Native/Modular (app.js, router.js, modules)
├── .htaccess           # Routing URL bersih
└── index.php           # Front Controller Utama
```

### 5.2. Logika "Sistem Efisiensi" (SPA via JavaScript)

Saat user *login*, PHP hanya merender *shell* utama (Navbar, Topbar, dan Kontainer Kosong). Saat user mengklik menu (misal "Mata Kuliah"), JavaScript akan melakukan `fetch('/api/courses')`, menerima data JSON, dan merender HTML menggunakan *DOM Manipulation* secara instan. Ini membuat aplikasi tidak pernah *blank white screen* saat pindah menu dan tidak memerlukan proses kompilasi tambahan di hosting.

### 5.3. Logika Rotasi API Gemini AI

Di backend PHP (`/api/generate_ai.php`), array API keys akan disimpan.

```php
$api_keys = [ $_ENV['GEMINI_KEY_1'], $_ENV['GEMINI_KEY_2'], $_ENV['GEMINI_KEY_3'] ];
```

Sistem akan mencoba melakukan request cURL menggunakan Index ke-0. Jika respons HTTP *Code* adalah `429 (Too Many Requests)` atau *Quota Exceeded*, sistem akan melakukan blok `try-catch` dan beralih ke Index ke-1, dan seterusnya, sebelum mengembalikan JSON ke frontend.

### 5.4. Logika Privasi "someone?"

Pada API yang mengembalikan daftar penyelesaian tugas (`/api/get_completions.php`), PHP akan mengecek kolom `is_anonymous` milik user.
Jika `TRUE`, respons JSON yang dikirimkan ke frontend adalah:
`{ name: "someone?", avatar: "default.png", role: "keroco", time: "10:30 PM" }`
Sehingga data asli tidak pernah bocor ke sisi *client* (browser).

### 5.5. Lazy Loading Tugas & Matkul

Frontend JavaScript akan menggunakan *Intersection Observer API*. Ketika pengguna men-scroll hingga elemen paling bawah di *div* tugas, JS akan mengirim *request* ke `GET /api/tasks?page=2&limit=10`. Spinner loading kecil muncul di bawah list, lalu data baru di-*append* ke dalam daftar yang sudah ada.

### 5.6. Skeleton Design

Aplikasi menggunakan skema skeleton design ketika baru masuk menu agar tidak terasa kosong saat data sedang dalam proses *fetch*.