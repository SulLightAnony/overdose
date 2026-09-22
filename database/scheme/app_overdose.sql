-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 22 Sep 2026 pada 07.53
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `app_overdose`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `courses`
--

CREATE TABLE `courses` (
  `courseId` int(11) NOT NULL,
  `semesterId` int(11) NOT NULL,
  `semesterNumber` int(11) NOT NULL,
  `courseCode` varchar(50) NOT NULL,
  `courseTitle` varchar(150) NOT NULL,
  `courseType` enum('Teori','Praktek') NOT NULL DEFAULT 'Teori',
  `courseClass` varchar(50) DEFAULT NULL,
  `courseDay` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') DEFAULT NULL,
  `startTime` time DEFAULT NULL,
  `endTime` time DEFAULT NULL,
  `courseDescription` text DEFAULT NULL,
  `lecturerName` varchar(150) DEFAULT NULL,
  `lecturerEmail` varchar(150) DEFAULT NULL,
  `lecturerPhone` varchar(50) DEFAULT NULL,
  `backgroundColor` varchar(50) NOT NULL DEFAULT '#10b981',
  `majorType` varchar(50) NOT NULL,
  `studyProgram` varchar(100) NOT NULL,
  `classGroup` varchar(50) NOT NULL,
  `batchYear` int(11) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `emailblacklists`
--

CREATE TABLE `emailblacklists` (
  `blacklistId` int(11) NOT NULL,
  `emailAddress` varchar(150) NOT NULL,
  `reasonDescription` text DEFAULT NULL,
  `addedByUserId` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `learning_material`
--

CREATE TABLE `learning_material` (
  `materialId` int(11) NOT NULL,
  `courseId` int(11) NOT NULL,
  `materialTitle` varchar(200) NOT NULL,
  `materialDescription` text DEFAULT NULL,
  `fileUrl` text NOT NULL,
  `uploadedByUserId` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `notificationId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `notificationTitle` varchar(150) NOT NULL,
  `notificationMessage` text NOT NULL,
  `relatedTaskId` int(11) DEFAULT NULL,
  `isRead` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `prodi_list`
--

CREATE TABLE `prodi_list` (
  `id` int(11) NOT NULL,
  `jurusan` varchar(100) NOT NULL,
  `nama_prodi` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `prodi_list`
--

INSERT INTO `prodi_list` (`id`, `jurusan`, `nama_prodi`) VALUES
(1, 'Teknik Sipil', 'D-3 Teknik Konstruksi Sipil'),
(2, 'Teknik Sipil', 'D-3 Teknik Konstruksi Gedung'),
(3, 'Teknik Sipil', 'D-4 Teknik Perancangan Jalan dan Jembatan'),
(4, 'Teknik Sipil', 'D-4 Teknik Perawatan dan Perbaikan Gedung'),
(5, 'Teknik Sipil', 'S-2 Rekayasa Infrastruktur'),
(6, 'Teknik Mesin', 'D-3 Teknik Mesin'),
(7, 'Teknik Mesin', 'D-3 Teknik Aeronautika'),
(8, 'Teknik Mesin', 'D-4 Teknik Perancangan dan Konstruksi Mesin'),
(9, 'Teknik Mesin', 'D-4 Proses Manufaktur'),
(10, 'Teknik Refrigerasi dan Tata Udara', 'D-3 Teknik Pendingin dan Tata Udara'),
(11, 'Teknik Refrigerasi dan Tata Udara', 'D-4 Teknik Pendingin dan Tata Udara'),
(12, 'Teknik Konversi Energi', 'D-3 Teknik Konversi Energi'),
(13, 'Teknik Konversi Energi', 'D-4 Teknologi Pembangkit Tenaga Listrik'),
(14, 'Teknik Konversi Energi', 'D-4 Teknik Konservasi Energi'),
(15, 'Teknik Elektro', 'D-3 Teknik Elektronika'),
(16, 'Teknik Elektro', 'D-3 Teknik Listrik'),
(17, 'Teknik Elektro', 'D-3 Teknik Telekomunikasi'),
(18, 'Teknik Elektro', 'D-4 Teknik Elektronika'),
(19, 'Teknik Elektro', 'D-4 Teknik Telekomunikasi'),
(20, 'Teknik Elektro', 'D-4 Teknik Otomasi Industri'),
(21, 'Teknik Kimia', 'D-3 Teknik Kimia'),
(22, 'Teknik Kimia', 'D-3 Analis Kimia'),
(23, 'Teknik Kimia', 'D-4 Teknik Kimia Produksi Bersih'),
(24, 'Teknik Komputer dan Informatika', 'D-3 Teknik Informatika'),
(25, 'Teknik Komputer dan Informatika', 'D-4 Teknik Informatika'),
(26, 'Akuntansi', 'D-3 Akuntansi'),
(27, 'Akuntansi', 'D-3 Keuangan dan Perbankan'),
(28, 'Akuntansi', 'D-4 Akuntansi Manajemen Pemerintahan'),
(29, 'Akuntansi', 'D-4 Akuntansi'),
(30, 'Akuntansi', 'D-4 Keuangan Syariah'),
(31, 'Akuntansi', 'S-2 Keuangan & Perbankan Syariah'),
(32, 'Administrasi Niaga', 'D-3 Administrasi Bisnis'),
(33, 'Administrasi Niaga', 'D-3 Manajemen Pemasaran'),
(34, 'Administrasi Niaga', 'D-3 Usaha Perjalanan Wisata'),
(35, 'Administrasi Niaga', 'D-4 Manajemen Aset'),
(36, 'Administrasi Niaga', 'D-4 Administrasi Bisnis'),
(37, 'Administrasi Niaga', 'D-4 Manajemen Pemasaran'),
(38, 'Administrasi Niaga', 'D-4 Destinasi Pariwisata'),
(39, 'Administrasi Niaga', 'S2 - Pemasaran, Inovasi, dan Teknologi'),
(40, 'Bahasa Inggris', 'D-3 Bahasa Inggris'),
(41, 'Bahasa Inggris', 'D-4 Bahasa Inggris untuk Komunikasi Bisnis dan Profesional');

-- --------------------------------------------------------

--
-- Struktur dari tabel `quote_list`
--

CREATE TABLE `quote_list` (
  `id` int(11) NOT NULL,
  `quote` text NOT NULL,
  `author` varchar(100) DEFAULT 'Unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quote_list`
--

INSERT INTO `quote_list` (`id`, `quote`, `author`) VALUES
(1, 'Tugas apapun tidak akan terasa berat — apabila tidak dikerjakan.', 'Almusayid'),
(2, 'Sebaik-baiknya deadline adalah yang masih besok.', 'Random'),
(3, 'Error adalah cara kode menyapamu. Jangan panik.', 'Random'),
(4, 'Tugas yang baik adalah tugas yang selesai, bukan yang hanya direnungkan.', 'Almusayid'),
(5, 'Error 404: Semangat belajar tidak ditemukan.', 'Random'),
(6, 'Tips: Minum air putih minimal 2 liter sehari agar otak tidak freeze saat ngoding.', 'Random'),
(7, 'Fun Fact: Bahasa pemrograman Python dinamai dari acara komedi BBC, Monty Python.', 'Unknown'),
(8, 'Jangan lupa git commit sebelum meninggalkan komputer.', 'Almusayid'),
(9, 'Sebaik-baiknya deadline adalah deadline yang masih besok.', 'Random'),
(10, 'Tips: Kalau stuck lebih dari 1 jam, jalan-jalan sebentar dan minum air hangat.', 'Unknown'),
(11, 'Satu-satunya cara membuat kode tanpa bug adalah tidak menulis kode sama sekali.', 'Almusayid'),
(12, 'Fun Fact: Bug komputer pertama di dunia adalah ngengat sungguhan yang terjebak di mesin Harvard Mark II.', 'Random'),
(13, 'Jangan suka menunda tugas, nanti tugasnya beranak pinak.', 'Unknown'),
(14, 'Kopi tidak menyelesaikan masalah, tapi membuatmu pusing dengan lebih cepat.', 'Almusayid'),
(15, 'Kalau ada kodingan jalan tapi kamu gak tahu kenapa, jangan pernah disentuh lagi.', 'Random'),
(16, 'Tips: Gunakan Ctrl + Z jika hidupmu terasa berantakan.', 'Unknown'),
(17, 'Dosen tidak membenci kodinganmu, mereka hanya membenci format laporanmu.', 'Almusayid'),
(18, 'Fun Fact: Karakter spasi mengambil memori di database. Hemat-hematlah spasi.', 'Random'),
(19, 'Semakin lama kamu menunda, semakin cepat deadline mendekat.', 'Unknown'),
(20, 'Makan siang berkarbohidrat tinggi bikin ngantuk pas kuliah siang. Pilih lauk berprotein.', 'Random'),
(21, 'Jangan pernah pamer kode yang belum siap di-deploy.', 'Almusayid'),
(22, 'Keyboard mekanik tidak membuat kodinganmu lebih cepat, hanya membuat ruangan lebih bising.', 'Random'),
(23, 'Fun Fact: Barcode pertama kali digunakan pada bungkus permen karet Wrigley.', 'Unknown'),
(24, 'Revisi adalah jalan ninja seorang mahasiswa.', 'Almusayid'),
(25, 'Tips: Pasang alarm dengan nada dering yang paling kamu benci agar pasti terbangun.', 'Random'),
(26, 'Kerapihan indentation menunjukkan kerapihan pikiran seorang programmer.', 'Unknown'),
(27, 'Jangan percaya pada pesan \"It works on my machine\".', 'Almusayid'),
(28, 'Fun Fact: Otak manusia menghasilkan listrik yang cukup untuk menyalakan lampu LED kecil.', 'Random'),
(29, 'Kerjakan tugas dari yang paling sulit dulu selagi otak masih segar.', 'Unknown'),
(30, 'Meriset error di Stack Overflow adalah skill utama seorang software engineer.', 'Almusayid'),
(31, 'Tidur 7 jam semalam lebih efektif daripada begadang belajar semalaman.', 'Random'),
(32, 'Jangan pakai nama variabel temp, x, atau data123 kalau tidak mau bingung besok.', 'Unknown'),
(33, 'Fun Fact: Metode Rubber Duck Debugging (menjelaskan kode ke bebek karet) terbukti efektif memecahkan logika.', 'Almusayid'),
(34, 'Semester tua bukanlah akhir dari dunia, tapi akhir dari masa santai.', 'Random'),
(35, 'Tips: Buat backup file tugasmu di cloud storage sebelum laptop berulah.', 'Unknown'),
(36, 'Kunci sukses presentasi: Pahami slide-mu, bukan hafalkan teksnya.', 'Almusayid'),
(37, 'Fun Fact: Bahasa C diciptakan oleh Dennis Ritchie antara tahun 1969 dan 1973.', 'Random'),
(38, 'Kodingan yang rapi adalah hadiah terbaik untuk dirimu di masa depan.', 'Unknown'),
(39, 'Kalau tugas kelompok, pastikan kamu bukan satu-satunya orang yang bekerja.', 'Almusayid'),
(40, 'Tips: Selalu cek koneksi internet sebelum memulai ujian online.', 'Random'),
(41, 'Belajar konsisten 30 menit sehari jauh lebih baik daripada 10 jam dalam semalam.', 'Unknown'),
(42, 'Fun Fact: Email diciptakan sebelum World Wide Web (WWW) lahir.', 'Almusayid'),
(43, 'Jangan lupa bersyukur kalau program berhasil di-compile tanpa error di run pertama.', 'Random'),
(44, 'Penundaan adalah pencuri waktu terhebat dalam perkuliahan.', 'Unknown'),
(45, 'Tips: Gunakan shortcut keyboard (Ctrl+C, Ctrl+V, Alt+Tab) untuk menghemat waktu kerja.', 'Almusayid'),
(46, 'Kuliah itu bukan cuma soal nilai IPK, tapi soal relasi dan pengalaman.', 'Random'),
(47, 'Fun Fact: Nama \"Google\" berasal dari kata \"Googol\", yaitu angka 1 yang diikuti oleh 100 angka nol.', 'Unknown'),
(48, 'Dokumentasi yang baik adalah tanda developer yang bertanggung jawab.', 'Almusayid'),
(49, 'Tips: Hindari minum kopi di atas jam 6 sore jika ingin tidur nyenyak.', 'Random'),
(50, 'Jangan lupa makan sebelum berangkat kuliah, otak butuh glukosa untuk berpikir.', 'Unknown'),
(51, 'Fun Fact: QWERTY diciptakan untuk memperlambat pengetikan agar mesin ketik zaman dulu tidak macet.', 'Almusayid'),
(52, 'Satu baris kode yang berfungsi jauh lebih baik daripada seribu baris rencana.', 'Random'),
(53, 'Gunakan waktu luangmu untuk mempelajari hal baru, bukan cuma scrolling sosmed.', 'Unknown'),
(54, 'First, solve the problem. Then, write the code.', 'Almusayid'),
(55, 'Experience is the name everyone gives to their mistakes.', 'Random'),
(56, 'Fun Fact: The first computer mouse was invented by Douglas Engelbart and was made of wood.', 'Unknown'),
(57, 'Simplicity is the soul of efficiency.', 'Almusayid'),
(58, 'Tips: Write clean comments to explain WHY you wrote the code, not WHAT the code is doing.', 'Random'),
(59, 'Make it work, make it right, make it fast.', 'Unknown'),
(60, 'Fun Fact: The total weight of all ants on Earth is roughly equal to the weight of all humans.', 'Random'),
(61, 'Code is like humor. When you have to explain it, it is bad.', 'Almusayid'),
(62, 'Don\'t cry because it\'s over, smile because it happened.', 'Unknown'),
(63, 'Tips: Take breaks! A 10-minute walk can boost your mental focus for hours.', 'Random'),
(64, 'Fun Fact: Venus is the only planet in solar system that rotates clockwise.', 'Unknown'),
(65, 'Talk is cheap. Show me the code.', 'Almusayid'),
(66, 'Software is a great combination between artistry and engineering.', 'Random'),
(67, 'Tips: Always double-check your email attachments before clicking send.', 'Unknown'),
(68, 'Fun Fact: Honey never spoils. Archeologists found 3,000-year-old edible honey in Egyptian tombs.', 'Almusayid'),
(69, 'Premature optimization is the root of all evil.', 'Random'),
(70, 'Testing leads to failure, and failure leads to understanding.', 'Unknown'),
(71, 'Fun Fact: Space is completely silent because there is no atmosphere to transmit sound.', 'Random'),
(72, 'Before software can be reusable it first has to be usable.', 'Almusayid'),
(73, 'Tips: Keep your workstation clean and organized to reduce daily stress.', 'Unknown'),
(74, 'Computers are fast; developers keep them slow.', 'Random'),
(75, 'Fun Fact: The domain name symbolics.com was the very first .com domain registered in 1985.', 'Almusayid'),
(76, 'The best error message is the one that never shows up.', 'Unknown'),
(77, 'Tips: Drink a glass of water right when you wake up to jumpstart your metabolism.', 'Random'),
(78, 'In order to be irreplaceable, one must always be different.', 'Almusayid'),
(79, 'Fun Fact: A group of flamingos is called a \"flamboyance\".', 'Unknown'),
(80, 'Good code is its own best documentation.', 'Random'),
(81, 'Tips: Use a password manager to keep your accounts secure and easy to access.', 'Almusayid'),
(82, 'Fix the cause, not the symptom.', 'Unknown'),
(83, 'Fun Fact: Bananas are naturally radioactive because they contain high levels of potassium.', 'Random'),
(84, '継続は力なり.', 'Almusayid'),
(85, '七転び八起き.', 'Unknown'),
(86, 'Fun Fact: 日本の自動販売機の数は世界一の密度です.', 'Random'),
(87, '千里の道も一歩から.', 'Almusayid'),
(88, '習うより慣れろ.', 'Unknown'),
(89, 'Tips: 作業中に目を休めるために「20-20-20の法則」を試してみましょう.', 'Random'),
(90, '初心忘るべからず .', 'Almusayid'),
(91, 'Fun Fact: 富士山は実は3つの火山が重なってできています.', 'Unknown'),
(92, '猿も木から落ちる.', 'Random'),
(93, '井の中の蛙大海を知らず .', 'Almusayid'),
(94, 'Tips: 睡眠不足はコードの品質を直接低下させます.', 'Unknown'),
(95, 'Fun Fact: 抹茶は緑茶の一種ですが、光を遮って育てられます.', 'Random'),
(96, '明日は明日の風が吹く', 'Almusayid'),
(97, '一期一会 ', 'Unknown'),
(98, 'Tips: 毎日小さなタスクを1つずつクリアしよう ', 'Random'),
(99, 'Fun Fact: パスワードの「123456」は世界で最も使われている危険なパスワードです ', 'Almusayid'),
(100, '失敗は成功のもと.', 'Unknown'),
(101, '塵も積もれば山となる .', 'Random'),
(102, 'Tips: デバッグに詰まったら、ラバーダックに説明してみよう .', 'Almusayid'),
(103, '一石二鳥 ', 'Unknown');

-- --------------------------------------------------------

--
-- Struktur dari tabel `semesters`
--

CREATE TABLE `semesters` (
  `semesterId` int(11) NOT NULL,
  `semesterNumber` int(11) NOT NULL,
  `semesterTitle` varchar(100) NOT NULL,
  `backgroundColor` varchar(50) NOT NULL DEFAULT '#3b82f6',
  `majorType` varchar(50) NOT NULL,
  `studyProgram` varchar(100) NOT NULL,
  `classGroup` varchar(50) NOT NULL,
  `batchYear` int(11) NOT NULL,
  `deletionStatus` tinyint(1) NOT NULL DEFAULT 0,
  `deletedByUserId` varchar(255) DEFAULT NULL,
  `createdByUserId` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `semesters`
--

INSERT INTO `semesters` (`semesterId`, `semesterNumber`, `semesterTitle`, `backgroundColor`, `majorType`, `studyProgram`, `classGroup`, `batchYear`, `deletionStatus`, `deletedByUserId`, `createdByUserId`, `createdAt`, `updatedAt`) VALUES
(1, 3, 'Semester 3 Ganjil', '#3b82f6', 'D4', 'Teknik Informatika', 'C', 2025, 0, NULL, 3, '2026-09-22 04:25:31', '2026-09-22 04:25:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `taskcompletions`
--

CREATE TABLE `taskcompletions` (
  `taskId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `completedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tasks`
--

CREATE TABLE `tasks` (
  `taskId` int(11) NOT NULL,
  `courseId` int(11) NOT NULL,
  `semesterId` int(11) NOT NULL,
  `taskType` varchar(20) NOT NULL,
  `taskTitle` varchar(200) NOT NULL,
  `taskDescription` text NOT NULL,
  `dueDate` datetime NOT NULL,
  `attachmentUrl` text DEFAULT NULL,
  `aiExplanation` text DEFAULT NULL,
  `createdByUserId` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `task_comments`
--

CREATE TABLE `task_comments` (
  `commentId` int(11) NOT NULL,
  `taskId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `commentContent` text NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `task_completions`
--

CREATE TABLE `task_completions` (
  `completionId` int(11) NOT NULL,
  `taskId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `completedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `googleId` varchar(255) NOT NULL,
  `userName` varchar(150) NOT NULL,
  `emailAddress` varchar(150) NOT NULL,
  `avatarUrl` text DEFAULT NULL,
  `roleLevel` enum('Primordial','Sepuh','Keroco') NOT NULL DEFAULT 'Keroco',
  `majorType` varchar(50) DEFAULT NULL,
  `studyProgram` varchar(100) DEFAULT NULL,
  `classGroup` varchar(50) DEFAULT NULL,
  `batchYear` int(11) DEFAULT NULL,
  `hideCompletedIdentity` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`userId`, `googleId`, `userName`, `emailAddress`, `avatarUrl`, `roleLevel`, `majorType`, `studyProgram`, `classGroup`, `batchYear`, `hideCompletedIdentity`, `createdAt`, `updatedAt`) VALUES
(3, '104787523545969827644', '1C_Sulthan Faazaa Akbar Riyandoro_088', 'sulthan.faazaa.tif425@polban.ac.id', 'https://lh3.googleusercontent.com/a/ACg8ocIIwwKwgHfrSBlcqcebKxmJqLb5ccXfSj-HjXASk_l5Ymp7XbQ=s96-c', 'Primordial', 'D4', 'Teknik Informatika', 'C', 2025, 0, '2026-09-22 02:56:23', '2026-09-22 04:25:08');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`courseId`),
  ADD KEY `semesterId` (`semesterId`);

--
-- Indeks untuk tabel `emailblacklists`
--
ALTER TABLE `emailblacklists`
  ADD PRIMARY KEY (`blacklistId`),
  ADD UNIQUE KEY `emailAddress` (`emailAddress`),
  ADD KEY `addedByUserId` (`addedByUserId`);

--
-- Indeks untuk tabel `learning_material`
--
ALTER TABLE `learning_material`
  ADD PRIMARY KEY (`materialId`),
  ADD KEY `courseId` (`courseId`),
  ADD KEY `uploadedByUserId` (`uploadedByUserId`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notificationId`),
  ADD KEY `userId` (`userId`),
  ADD KEY `relatedTaskId` (`relatedTaskId`);

--
-- Indeks untuk tabel `prodi_list`
--
ALTER TABLE `prodi_list`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `quote_list`
--
ALTER TABLE `quote_list`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semesterId`),
  ADD KEY `createdByUserId` (`createdByUserId`);

--
-- Indeks untuk tabel `taskcompletions`
--
ALTER TABLE `taskcompletions`
  ADD PRIMARY KEY (`taskId`,`userId`);

--
-- Indeks untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`taskId`),
  ADD KEY `courseId` (`courseId`),
  ADD KEY `createdByUserId` (`createdByUserId`),
  ADD KEY `semesterId` (`semesterId`);

--
-- Indeks untuk tabel `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`commentId`),
  ADD KEY `taskId` (`taskId`),
  ADD KEY `userId` (`userId`);

--
-- Indeks untuk tabel `task_completions`
--
ALTER TABLE `task_completions`
  ADD PRIMARY KEY (`completionId`),
  ADD UNIQUE KEY `uniqueUserTask` (`userId`,`taskId`),
  ADD KEY `taskId` (`taskId`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `googleId` (`googleId`),
  ADD UNIQUE KEY `emailAddress` (`emailAddress`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `courses`
--
ALTER TABLE `courses`
  MODIFY `courseId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `emailblacklists`
--
ALTER TABLE `emailblacklists`
  MODIFY `blacklistId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `learning_material`
--
ALTER TABLE `learning_material`
  MODIFY `materialId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notificationId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `prodi_list`
--
ALTER TABLE `prodi_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT untuk tabel `quote_list`
--
ALTER TABLE `quote_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT untuk tabel `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semesterId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `tasks`
--
ALTER TABLE `tasks`
  MODIFY `taskId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `commentId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `task_completions`
--
ALTER TABLE `task_completions`
  MODIFY `completionId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_semesters` FOREIGN KEY (`semesterId`) REFERENCES `semesters` (`semesterId`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `emailblacklists`
--
ALTER TABLE `emailblacklists`
  ADD CONSTRAINT `emailblacklists_ibfk_1` FOREIGN KEY (`addedByUserId`) REFERENCES `users` (`userId`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `learning_material`
--
ALTER TABLE `learning_material`
  ADD CONSTRAINT `learning_material_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON DELETE CASCADE,
  ADD CONSTRAINT `learning_material_ibfk_2` FOREIGN KEY (`uploadedByUserId`) REFERENCES `users` (`userId`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`relatedTaskId`) REFERENCES `tasks` (`taskId`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `semesters_ibfk_1` FOREIGN KEY (`createdByUserId`) REFERENCES `users` (`userId`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_semesters` FOREIGN KEY (`semesterId`) REFERENCES `semesters` (`semesterId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `courses` (`courseId`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`createdByUserId`) REFERENCES `users` (`userId`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `task_comments`
--
ALTER TABLE `task_comments`
  ADD CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`taskId`) REFERENCES `tasks` (`taskId`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `task_completions`
--
ALTER TABLE `task_completions`
  ADD CONSTRAINT `task_completions_ibfk_1` FOREIGN KEY (`taskId`) REFERENCES `tasks` (`taskId`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_completions_ibfk_2` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
