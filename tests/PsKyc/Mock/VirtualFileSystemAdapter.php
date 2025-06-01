<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace Tests\PsKyc\Mock;

/**
 * Virtual File System Implementation
 *
 * Implementation of FileSystemInterface using vfsStream for testing
 */
class VirtualFileSystemAdapter implements \FileSystemInterface
{
    /**
     * @var VirtualFileSystem
     */
    private $vfs;

    public function __construct(VirtualFileSystem $vfs)
    {
        $this->vfs = $vfs;
    }

    public function fileExists(string $path): bool
    {
        return $this->vfs->fileExists($path);
    }

    public function getFileContents(string $path): string
    {
        return $this->vfs->getFileContent($path);
    }

    public function putFileContents(string $path, string $contents): int
    {
        return file_put_contents($path, $contents);
    }

    public function deleteFile(string $path): bool
    {
        return $this->vfs->deleteFile($path);
    }

    public function getFileSize(string $path): int
    {
        return $this->vfs->getFileSize($path);
    }

    public function getMimeType(string $path): string
    {
        // Use actual finfo for realistic MIME detection in virtual file system
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    public function createDirectory(string $path, int $permissions = 0755): bool
    {
        return mkdir($path, $permissions, true);
    }

    public function getUploadDirectory(): string
    {
        return $this->vfs->getUploadDirectory();
    }

    public function rename(string $oldPath, string $newPath): bool
    {
        return rename($oldPath, $newPath);
    }
}
