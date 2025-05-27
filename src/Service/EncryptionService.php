<?php
namespace PrestaShop\Module\Pskyc\Service;
use Configuration;
/**
 * Class EncryptionService
 * 
 * Handles file encryption and decryption for secure storage of KYC documents
 * Uses AES-256-CBC encryption with random initialization vectors
 */
class EncryptionService
{
    /**
     * Encryption algorithm used for file encryption
     */
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Length of the initialization vector in bytes
     */
    private const IV_LENGTH = 16;

    /**
     * Get encryption key from configuration or environment
     * 
     * Retrieves the encryption key used for file encryption/decryption
     * The key should be stored securely and not in the database
     * 
     * @return string The encryption key
     * @throws \RuntimeException If encryption key is not configured
     */
    private function getEncryptionKey(): string
    {
        $key = Configuration::get('PSKYC_ENCRYPTION_KEY');
        
        if (empty($key)) {
            // Generate a new key if none exists
            $key = bin2hex(random_bytes(32)); // 256-bit key
            Configuration::updateValue('PSKYC_ENCRYPTION_KEY', $key);
        }

        return hex2bin($key);
    }

    /**
     * Generate a random initialization vector
     * 
     * Creates a random IV for encryption to ensure each encrypted file
     * has unique ciphertext even with identical plaintext
     * 
     * @return string Base64-encoded initialization vector
     */
    public function generateIV(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(self::IV_LENGTH));
    }

    /**
     * Encrypt file contents
     * 
     * Encrypts the provided data using AES-256-CBC encryption
     * 
     * @param string $data The data to encrypt
     * @param string $iv Base64-encoded initialization vector
     * @return string The encrypted data (base64-encoded)
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $data, string $iv): string
    {
        $key = $this->getEncryptionKey();
        $ivBinary = base64_decode($iv);

        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $ivBinary);

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt data');
        }

        return $encrypted;
    }

    /**
     * Decrypt file contents
     * 
     * Decrypts previously encrypted data using the same algorithm and key
     * 
     * @param string $encryptedData Base64-encoded encrypted data
     * @param string $iv Base64-encoded initialization vector used for encryption
     * @return string The decrypted data
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encryptedData, string $iv): string
    {
        $key = $this->getEncryptionKey();
        $ivBinary = base64_decode($iv);

        $decrypted = openssl_decrypt($encryptedData, self::ENCRYPTION_METHOD, $key, 0, $ivBinary);

        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt data');
        }

        return $decrypted;
    }

    /**
     * Encrypt file and save to disk
     * 
     * Encrypts a file and saves it to the specified location
     * 
     * @param string $sourceFilePath Path to the source file to encrypt
     * @param string $destinationPath Path where encrypted file should be saved
     * @return array Array containing 'iv' and 'sha256' of original file
     * @throws \RuntimeException If file operations fail
     */
    public function encryptFile(string $sourceFilePath, string $destinationPath): array
    {
        if (!file_exists($sourceFilePath)) {
            throw new \RuntimeException('Source file does not exist: ' . $sourceFilePath);
        }

        $data = file_get_contents($sourceFilePath);
        if ($data === false) {
            throw new \RuntimeException('Failed to read source file: ' . $sourceFilePath);
        }

        $sha256 = hash('sha256', $data);
        $iv = $this->generateIV();
        $encryptedData = $this->encrypt($data, $iv);

        if (file_put_contents($destinationPath, $encryptedData) === false) {
            throw new \RuntimeException('Failed to write encrypted file: ' . $destinationPath);
        }

        return [
            'iv' => $iv,
            'sha256' => $sha256
        ];
    }

    /**
     * Decrypt file from disk
     * 
     * Reads an encrypted file from disk and decrypts it
     * 
     * @param string $encryptedFilePath Path to the encrypted file
     * @param string $iv Base64-encoded initialization vector
     * @return string The decrypted file contents
     * @throws \RuntimeException If file operations or decryption fail
     */
    public function decryptFile(string $encryptedFilePath, string $iv): string
    {
        if (!file_exists($encryptedFilePath)) {
            throw new \RuntimeException('Encrypted file does not exist: ' . $encryptedFilePath);
        }

        $encryptedData = file_get_contents($encryptedFilePath);
        if ($encryptedData === false) {
            throw new \RuntimeException('Failed to read encrypted file: ' . $encryptedFilePath);
        }

        return $this->decrypt($encryptedData, $iv);
    }

    /**
     * Verify file integrity
     * 
     * Checks if a decrypted file matches its expected SHA256 hash
     * 
     * @param string $data The decrypted file data
     * @param string $expectedSha256 The expected SHA256 hash
     * @return bool True if hashes match, false otherwise
     */
    public function verifyIntegrity(string $data, string $expectedSha256): bool
    {
        return hash('sha256', $data) === $expectedSha256;
    }

    /**
     * Securely delete file
     * 
     * Overwrites file contents with random data before deletion
     * 
     * @param string $filePath Path to the file to securely delete
     * @return bool True if file was successfully deleted, false otherwise
     */
    public function secureDelete(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return true; // File doesn't exist, consider it deleted
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            return false;
        }

        // Overwrite with random data
        $handle = fopen($filePath, 'r+b');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, openssl_random_pseudo_bytes($fileSize));
        fclose($handle);

        return unlink($filePath);
    }
}