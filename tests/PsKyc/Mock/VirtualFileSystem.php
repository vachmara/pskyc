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

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * Virtual File System Helper for DocumentService Testing
 *
 * Provides a clean abstraction over vfsStream for testing file operations
 * without touching the real file system.
 */
class VirtualFileSystem
{
    /**
     * @var vfsStreamDirectory
     */
    private $root;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * Initialize virtual file system with default structure
     */
    public function __construct()
    {
        $this->setupFileSystem();
    }

    /**
     * Set up the virtual file system structure
     */
    private function setupFileSystem(): void
    {
        $structure = [
            'secure_upload' => [],
            'tmp' => [],
        ];

        $this->root = vfsStream::setup('pskyc', 0755, $structure);
        $this->rootPath = vfsStream::url('pskyc');
    }

    /**
     * Get the upload directory path
     */
    public function getUploadDirectory(): string
    {
        return $this->rootPath . '/secure_upload';
    }

    /**
     * Get the tmp directory path
     */
    public function getTmpDirectory(): string
    {
        return $this->rootPath . '/tmp';
    }

    /**
     * Create a test file with specified content and MIME type
     */
    public function createTestFile(string $filename, string $content, string $mimeType = 'image/jpeg'): string
    {
        $filePath = $this->getTmpDirectory() . '/' . $filename;

        // Create content based on MIME type for realistic testing
        $fileContent = $this->generateFileContent($content, $mimeType);

        file_put_contents($filePath, $fileContent);

        return $filePath;
    }

    /**
     * Create an encrypted document file in secure upload directory
     */
    public function createEncryptedDocument(int $documentId, string $filename, string $encryptedContent): string
    {
        $storedFilename = 'doc_' . $documentId . '_' . hash('md5', $filename);
        $filePath = $this->getUploadDirectory() . '/' . $storedFilename;

        file_put_contents($filePath, $encryptedContent);

        return $filePath;
    }

    /**
     * Check if a file exists in the virtual file system
     */
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Get file size
     */
    public function getFileSize(string $path): int
    {
        return filesize($path);
    }

    /**
     * Delete a file from virtual file system
     */
    public function deleteFile(string $path): bool
    {
        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Get file content
     */
    public function getFileContent(string $path): string
    {
        return file_get_contents($path);
    }

    /**
     * Generate realistic file content based on MIME type
     */
    private function generateFileContent(string $baseContent, string $mimeType): string
    {
        switch ($mimeType) {
            case 'image/jpeg':
                // JPEG file header
                return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB" . $baseContent;

            case 'image/png':
                // PNG file header
                return "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR" . $baseContent;

            case 'application/pdf':
                // PDF file header
                return "%PDF-1.4\n" . $baseContent . "\n%%EOF";

            case 'text/plain':
                return $baseContent;

            default:
                return $baseContent;
        }
    }

    /**
     * Reset the virtual file system (useful between tests)
     */
    public function reset(): void
    {
        $this->setupFileSystem();
    }

    /**
     * Create a file with specific permissions for testing permission errors
     */
    public function createFileWithPermissions(string $filename, string $content, int $permissions): string
    {
        $filePath = $this->getTmpDirectory() . '/' . $filename;
        file_put_contents($filePath, $content);
        chmod($filePath, $permissions);

        return $filePath;
    }

    /**
     * Create a directory with specific permissions
     */
    public function createDirectoryWithPermissions(string $dirname, int $permissions): string
    {
        $dirPath = $this->rootPath . '/' . $dirname;
        mkdir($dirPath, $permissions, true);

        return $dirPath;
    }

    /**
     * Simulate disk full by creating a directory with limited space
     * (This is a simulation - vfsStream doesn't enforce actual disk limits)
     */
    public function simulateDiskFull(): void
    {
        // In a real implementation, you might use vfsStream's quota feature
        // For now, we'll just create a marker that our service can check
        file_put_contents($this->rootPath . '/.disk_full', 'true');
    }

    /**
     * Check if disk full simulation is active
     */
    public function isDiskFull(): bool
    {
        return file_exists($this->rootPath . '/.disk_full');
    }
}
