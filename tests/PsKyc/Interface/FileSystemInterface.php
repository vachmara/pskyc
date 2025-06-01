<?php

/**
 * File System Interface for Dependency Injection
 *
 * Allows us to inject different file system implementations
 * (real file system vs virtual file system for testing)
 */
interface FileSystemInterface
{
    /**
     * Check if a file exists
     */
    public function fileExists(string $path): bool;

    /**
     * Get file contents
     */
    public function getFileContents(string $path): string;

    /**
     * Put contents to a file
     */
    public function putFileContents(string $path, string $contents): int;

    /**
     * Delete a file
     */
    public function deleteFile(string $path): bool;

    /**
     * Get file size
     */
    public function getFileSize(string $path): int;

    /**
     * Get MIME type of a file
     */
    public function getMimeType(string $path): string;

    /**
     * Create directory if it doesn't exist
     */
    public function createDirectory(string $path, int $permissions = 0755): bool;

    /**
     * Get upload directory path
     */
    public function getUploadDirectory(): string;
}
