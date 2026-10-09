<?php
/**
 * ====================================================================================
 * MODULE: Cloud Storage Integration - Google Drive Manager
 * FILE LOCATION: app/api/DriveManager.php
 * ====================================================================================
 *
 * TUJUAN (PURPOSE):
 * Mengelola interaksi dengan Google Drive API v3 secara terpusat:
 * - Autentikasi OAuth 2.0 (Akun Pribadi Google One 5TB) dengan auto-refresh token yang aman.
 * - Caching lokal ID folder basis (OverdoseUploads -> Tasks, Materials, Answers) untuk performa tinggi.
 * - Pembuatan folder terdedikasi per entitas [userId]_[entityId]_[Y-m-d-H-i-s] dengan izin publik reader.
 * - Streaming upload file dengan validasi ketat whitelist ekstensi & MIME type serta penanganan duplikat nama.
 * - Penghapusan satuan berkas dan cascade folder penghapusan entitas dengan batas waktu eksekusi aman.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class DriveManager
{
    private static ?DriveManager $instance = null;
    private Google_Client $client;
    private Google_Service_Drive $service;
    private ?string $rootFolderId = null;
    private array $categoryFolderIds = [];

    private function __construct()
    {
        $oauthClientPath = realpath(__DIR__ . '/../../database/drive_storage/oauth_client.json');
        $oauthTokenPath  = realpath(__DIR__ . '/../../database/drive_storage/oauth_token.json');

        $this->client = new Google_Client();
        $this->client->addScope(\Google\Service\Drive::DRIVE);
        $this->client->setApplicationName('Overdose Application');

        if ($oauthClientPath && file_exists($oauthClientPath) && $oauthTokenPath && file_exists($oauthTokenPath)) {
            // Mode Akun Pribadi (Google One 5TB via OAuth 2.0)
            $this->client->setAuthConfig($oauthClientPath);
            $this->client->setAccessType('offline');
            $accessToken = json_decode(file_get_contents($oauthTokenPath), true) ?: [];
            $this->client->setAccessToken($accessToken);

            if ($this->client->isAccessTokenExpired()) {
                $refreshToken = $this->client->getRefreshToken() ?: ($accessToken['refresh_token'] ?? null);
                if ($refreshToken) {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    if (isset($newToken['error'])) {
                        throw new RuntimeException('Otorisasi Google Drive kadaluwarsa atau ditolak: ' . ($newToken['error_description'] ?? $newToken['error']) . '. Silakan lakukan otorisasi ulang melalui oauth_authorize.php.');
                    }
                    if (isset($newToken['access_token'])) {
                        $updatedToken = array_merge($accessToken, $this->client->getAccessToken());
                        if (empty($updatedToken['refresh_token']) && !empty($refreshToken)) {
                            $updatedToken['refresh_token'] = $refreshToken;
                        }
                        file_put_contents($oauthTokenPath, json_encode($updatedToken, JSON_PRETTY_PRINT), LOCK_EX);
                    }
                }
            }
        } else {
            // Mode Service Account Fallback
            $credPath = realpath(__DIR__ . '/../../database/drive_storage/overdose-drive-storage-a4fbbc1129f3.json');
            if (!$credPath || !file_exists($credPath)) {
                throw new RuntimeException('Berkas kredensial Google Drive tidak ditemukan.');
            }
            $this->client->setAuthConfig($credPath);
        }

        $this->service = new Google_Service_Drive($this->client);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getService(): Google_Service_Drive
    {
        return $this->service;
    }

    /**
     * Membaca berkas cache ID folder lokal
     */
    private function getFolderCache(): array
    {
        $cachePath = __DIR__ . '/../../database/drive_storage/folders_cache.json';
        if (file_exists($cachePath)) {
            $data = json_decode(file_get_contents($cachePath), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    /**
     * Menyimpan ID folder ke cache lokal
     */
    private function updateFolderCache(string $key, string $id): void
    {
        $cachePath = __DIR__ . '/../../database/drive_storage/folders_cache.json';
        $cache = $this->getFolderCache();
        $cache[$key] = $id;
        @file_put_contents($cachePath, json_encode($cache, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Resolusi folder root OverdoseUploads dengan cache lokal
     */
    public function getRootFolderId(): string
    {
        if ($this->rootFolderId !== null) {
            return $this->rootFolderId;
        }

        $cache = $this->getFolderCache();
        if (!empty($cache['root'])) {
            $this->rootFolderId = $cache['root'];
            return $this->rootFolderId;
        }

        $q = "name = 'OverdoseUploads' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        $res = $this->service->files->listFiles([
            'q'                         => $q,
            'fields'                    => 'files(id, name)',
            'supportsAllDrives'         => true,
            'includeItemsFromAllDrives' => true,
            'pageSize'                  => 1
        ]);

        $files = $res->getFiles();
        if (!empty($files)) {
            $this->rootFolderId = $files[0]->getId();
            $this->updateFolderCache('root', $this->rootFolderId);
            return $this->rootFolderId;
        }

        $folderMetadata = new Google_Service_Drive_DriveFile();
        $folderMetadata->setName('OverdoseUploads');
        $folderMetadata->setMimeType('application/vnd.google-apps.folder');

        $created = $this->service->files->create($folderMetadata, [
            'supportsAllDrives' => true,
            'fields'            => 'id'
        ]);

        $this->rootFolderId = $created->id;
        $this->updateFolderCache('root', $this->rootFolderId);
        return $this->rootFolderId;
    }

    /**
     * Resolusi subfolder kategori (Tasks, Materials, Answers) dengan cache lokal
     */
    public function getCategoryFolderId(string $category): string
    {
        if (isset($this->categoryFolderIds[$category])) {
            return $this->categoryFolderIds[$category];
        }

        $cacheKey = 'category_' . $category;
        $cache = $this->getFolderCache();
        if (!empty($cache[$cacheKey])) {
            $this->categoryFolderIds[$category] = $cache[$cacheKey];
            return $this->categoryFolderIds[$category];
        }

        $rootId = $this->getRootFolderId();
        $q = sprintf(
            "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            addslashes($category),
            $rootId
        );

        $res = $this->service->files->listFiles([
            'q'                         => $q,
            'fields'                    => 'files(id, name)',
            'supportsAllDrives'         => true,
            'includeItemsFromAllDrives' => true,
            'pageSize'                  => 1
        ]);

        $files = $res->getFiles();
        if (!empty($files)) {
            $catId = $files[0]->getId();
            $this->categoryFolderIds[$category] = $catId;
            $this->updateFolderCache($cacheKey, $catId);
            return $catId;
        }

        $folderMetadata = new Google_Service_Drive_DriveFile();
        $folderMetadata->setName($category);
        $folderMetadata->setMimeType('application/vnd.google-apps.folder');
        $folderMetadata->setParents([$rootId]);

        $created = $this->service->files->create($folderMetadata, [
            'supportsAllDrives' => true,
            'fields'            => 'id'
        ]);

        $this->categoryFolderIds[$category] = $created->id;
        $this->updateFolderCache($cacheKey, $created->id);
        return $created->id;
    }

    /**
     * Resolusi dedicated entity folder:
     * Format: [userId]_[entityId]_[Y-m-d-H-i-s]
     */
    public function resolveEntityFolder(string $category, ?string $existingFolderId, int $userId, int $entityId): string
    {
        if (!empty($existingFolderId)) {
            return $existingFolderId;
        }

        $categoryParentId = $this->getCategoryFolderId($category);
        $folderName = sprintf('%d_%d_%s', $userId, $entityId, date('Y-m-d-H-i-s'));

        $folderMetadata = new Google_Service_Drive_DriveFile();
        $folderMetadata->setName($folderName);
        $folderMetadata->setMimeType('application/vnd.google-apps.folder');
        $folderMetadata->setParents([$categoryParentId]);

        $created = $this->service->files->create($folderMetadata, [
            'supportsAllDrives' => true,
            'fields'            => 'id, name'
        ]);

        // Berikan hak akses reader publik pada folder entitas sekali saja
        // Berkas di dalam folder ini otomatis mewarisi (inherit) hak akses publik
        try {
            $permission = new Google_Service_Drive_Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $this->service->permissions->create($created->id, $permission, [
                'supportsAllDrives' => true
            ]);
        } catch (Throwable $permError) {
            // Izin folder publik gagal tidak membatalkan alur
        }

        return $created->id;
    }

    /**
     * Stream upload files dari $_FILES ke Google Drive dengan validasi ketat
     */
    public function uploadFiles(string $folderId, array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $existingNames = $this->listFileNamesInFolder($folderId);
        $uploadedResults = [];
        $uploadedFileIds = [];
        $fileInfo = @finfo_open(FILEINFO_MIME_TYPE);

        $allowedMimesByExt = [
            'pdf'  => ['application/pdf', 'application/x-pdf'],
            'doc'  => ['application/msword', 'application/octet-stream', 'application/vnd.ms-word'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream', 'application/x-zip', 'application/x-zip-compressed'],
            'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream', 'application/x-zip', 'application/x-zip-compressed'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream', 'application/x-zip', 'application/x-zip-compressed'],
            'txt'  => ['text/plain', 'text/x-c', 'text/x-c++'],
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png', 'image/x-png'],
            'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/x-zip', 'application/octet-stream', 'multipart/x-zip'],
            'rar'  => ['application/x-rar-compressed', 'application/octet-stream', 'application/vnd.rar']
        ];
        $maxFileSize = 10 * 1024 * 1024; // 10MB

        try {
            foreach ($files['name'] as $index => $rawName) {
                $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                    throw new RuntimeException('Ukuran berkas ' . basename((string)$rawName) . ' melebihi batas maksimal 10MB.');
                }
                if ($error !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Pengunggahan berkas ' . basename((string)$rawName) . ' gagal.');
                }

                $tmpPath = $files['tmp_name'][$index] ?? '';
                $fileSize = (int)($files['size'][$index] ?? 0);
                $cleanName = basename((string)$rawName);
                $extension = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));

                if (!is_uploaded_file($tmpPath) || $fileSize <= 0) {
                    throw new RuntimeException('Berkas tidak valid: ' . $cleanName);
                }
                if ($fileSize > $maxFileSize) {
                    throw new RuntimeException('Berkas ' . $cleanName . ' melebihi batas maksimal 10MB.');
                }

                // 1. Validasi Whitelist Ekstensi
                if (!isset($allowedMimesByExt[$extension])) {
                    throw new RuntimeException('Tipe berkas .' . htmlspecialchars($extension) . ' tidak diizinkan untuk diunggah (' . htmlspecialchars($cleanName) . ').');
                }

                // 2. Deteksi dan Validasi MIME Type
                $mimeType = false;
                if ($fileInfo !== false) {
                    $mimeType = finfo_file($fileInfo, $tmpPath);
                } elseif (function_exists('mime_content_type')) {
                    $mimeType = mime_content_type($tmpPath);
                }
                if (!$mimeType) {
                    $mimeType = 'application/octet-stream';
                }

                $allowedList = $allowedMimesByExt[$extension];
                if (!in_array($mimeType, $allowedList, true) && $mimeType !== 'application/octet-stream') {
                    throw new RuntimeException('Format konten berkas tidak sesuai dengan ekstensinya: ' . htmlspecialchars($cleanName));
                }

                $uniqueName = $this->getUniqueFileName($cleanName, $existingNames);
                $existingNames[] = $uniqueName;

                $fileMetadata = new Google_Service_Drive_DriveFile();
                $fileMetadata->setName($uniqueName);
                $fileMetadata->setParents([$folderId]);

                $fileContent = file_get_contents($tmpPath);
                if ($fileContent === false) {
                    throw new RuntimeException('Gagal membaca berkas: ' . $cleanName);
                }

                $uploadedDriveFile = $this->service->files->create($fileMetadata, [
                    'data'              => $fileContent,
                    'mimeType'          => $mimeType,
                    'uploadType'        => 'multipart',
                    'supportsAllDrives' => true,
                    'fields'            => 'id, name, webViewLink, webContentLink, size'
                ]);

                $uploadedFileIds[] = $uploadedDriveFile->id;

                // Terapkan izin akses publik per-file sebagai jaminan redundansi
                try {
                    $permission = new Google_Service_Drive_Permission();
                    $permission->setType('anyone');
                    $permission->setRole('reader');
                    $this->service->permissions->create($uploadedDriveFile->id, $permission, [
                        'supportsAllDrives' => true
                    ]);
                } catch (Throwable $permError) {
                    // Izin publik per-file gagal diabaikan karena folder induk sudah berizin publik
                }

                $viewUrl = $uploadedDriveFile->webViewLink ?: ('https://drive.google.com/file/d/' . $uploadedDriveFile->id . '/view');
                $downloadUrl = 'https://drive.google.com/uc?export=download&id=' . $uploadedDriveFile->id;

                $uploadedResults[] = [
                    'fileId'      => $uploadedDriveFile->id,
                    'fileName'    => $uniqueName,
                    'filePath'    => $viewUrl,
                    'downloadUrl' => $downloadUrl,
                    'fileSize'    => (int)($uploadedDriveFile->size ?: $fileSize)
                ];
            }
        } catch (Throwable $e) {
            // Rollback semua file yang sudah terunggah pada batch ini jika terjadi kegagalan
            foreach ($uploadedFileIds as $idToRollback) {
                try {
                    $this->service->files->delete($idToRollback, ['supportsAllDrives' => true]);
                } catch (Throwable $ignored) {}
            }

            if ($e instanceof Google_Service_Exception) {
                $rawMsg = $e->getMessage();
                if (str_contains($rawMsg, 'storageQuotaExceeded') || str_contains($rawMsg, 'Service Accounts do not have storage quota')) {
                    throw new RuntimeException('Google Drive Kuota Terlampaui: Akun Service Account tidak memiliki kuota penyimpanan pribadi di My Drive.');
                }
                if (str_contains($rawMsg, 'invalid_grant')) {
                    throw new RuntimeException('Sesi otorisasi Google Drive telah kadaluwarsa. Silakan lakukan otorisasi ulang.');
                }
            }

            throw $e;
        }

        return $uploadedResults;
    }

    /**
     * Hapus berkas satuan berdasarkan File ID atau URL
     */
    public function deleteFile(string $fileIdOrUrl): bool
    {
        $fileId = self::extractFileIdFromUrl($fileIdOrUrl);
        if (!$fileId) {
            return false;
        }

        try {
            $this->service->files->delete($fileId, [
                'supportsAllDrives' => true
            ]);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * Hapus folder Google Drive beserta isinya
     */
    public function deleteFolder(?string $folderId): bool
    {
        if (empty($folderId)) {
            return true;
        }

        try {
            $this->service->files->delete($folderId, [
                'supportsAllDrives' => true
            ]);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * Hapus banyak folder Google Drive dengan batas waktu eksekusi aman
     */
    public function deleteFolders(array $folderIds): void
    {
        @set_time_limit(180);
        $cleanIds = array_unique(array_filter($folderIds, fn($id) => !empty($id) && is_string($id)));
        foreach ($cleanIds as $id) {
            $this->deleteFolder($id);
        }
    }

    /**
     * Memeriksa apakah array $_FILES berisi setidaknya satu file yang diunggah
     */
    public static function hasFiles(?array $files): bool
    {
        if (!$files || !isset($files['name'])) {
            return false;
        }
        if (is_array($files['name'])) {
            foreach ($files['error'] as $err) {
                if ((int)$err !== UPLOAD_ERR_NO_FILE) {
                    return true;
                }
            }
            return false;
        }
        return ((int)($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    }

    /**
     * Ekstraksi Google Drive File ID dari URL
     */
    public static function extractFileIdFromUrl(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^[a-zA-Z0-9_-]{25,}$/', $url)) {
            return $url;
        }
        return null;
    }

    private function listFileNamesInFolder(string $folderId): array
    {
        $names = [];
        try {
            $q = sprintf("'%s' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'", $folderId);
            $res = $this->service->files->listFiles([
                'q'                         => $q,
                'fields'                    => 'files(name)',
                'supportsAllDrives'         => true,
                'includeItemsFromAllDrives' => true,
                'pageSize'                  => 100
            ]);
            foreach ($res->getFiles() as $file) {
                $names[] = $file->getName();
            }
        } catch (Throwable $e) {
            // Abaikan kesalahan pembacaan nama
        }
        return $names;
    }

    private function getUniqueFileName(string $originalName, array $existingNames): string
    {
        if (!in_array($originalName, $existingNames, true)) {
            return $originalName;
        }

        $dotIndex = strrpos($originalName, '.');
        $baseName = $dotIndex !== false ? substr($originalName, 0, $dotIndex) : $originalName;
        $extension = $dotIndex !== false ? substr($originalName, $dotIndex) : '';

        $counter = 1;
        $candidate = "{$baseName} ({$counter}){$extension}";
        while (in_array($candidate, $existingNames, true)) {
            $counter++;
            $candidate = "{$baseName} ({$counter}){$extension}";
        }

        return $candidate;
    }
}
