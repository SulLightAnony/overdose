-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 21 Sep 2026 pada 09.51
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
  `semesterNumber` int(11) NOT NULL,
  `courseCode` varchar(50) NOT NULL,
  `courseTitle` varchar(150) NOT NULL,
  `courseDescription` text DEFAULT NULL,
  `lecturerName` varchar(150) DEFAULT NULL,
  `lecturerEmail` varchar(150) DEFAULT NULL,
  `lecturerPhone` varchar(50) DEFAULT NULL,
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
  `createdByUserId` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tasks`
--

CREATE TABLE `tasks` (
  `taskId` int(11) NOT NULL,
  `courseId` int(11) NOT NULL,
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
  `roleLevel` enum('primordial','sepuh','member') NOT NULL DEFAULT 'member',
  `majorType` varchar(50) DEFAULT NULL,
  `studyProgram` varchar(100) DEFAULT NULL,
  `classGroup` varchar(50) DEFAULT NULL,
  `batchYear` int(11) DEFAULT NULL,
  `hideCompletedIdentity` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`courseId`);

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
-- Indeks untuk tabel `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semesterId`),
  ADD KEY `createdByUserId` (`createdByUserId`);

--
-- Indeks untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`taskId`),
  ADD KEY `courseId` (`courseId`),
  ADD KEY `createdByUserId` (`createdByUserId`);

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
  MODIFY `courseId` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT untuk tabel `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semesterId` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

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
