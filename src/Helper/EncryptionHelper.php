<?php
namespace PrestaShop\Module\Pskyc\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Helper class for AES-256-CBC encryption, decryption, and secure file handling.
 *
 * @package PrestaShop\Module\Pskyc\Helper
 */
class EncryptionHelper
{
    private const METHOD = 'AES-256-CBC';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 16;  // 128 bits

    /**
     * Generates a random 256-bit encryption key (hex encoded)
     *
     * @return string 64-character hex string
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(self::KEY_LENGTH));
    }

    /**
     * Generates a random 128-bit initialization vector (IV) (hex encoded)
     *
     * @return string 32-character hex string
     */
    public static function generateIv(): string
    {
        return bin2hex(random_bytes(self::IV_LENGTH));
    }

    /**
     * Encrypt raw file content using AES-256-CBC
     *
     * @param string $content Raw content to encrypt
     * @param string $key     64-char hex key
     * @param string $iv      32-char hex IV
     * @return string|false   Encrypted binary string or false on failure
     */
    public static function encrypt(string $content, string $key, string $iv)
    {
        $result = openssl_encrypt($content, self::METHOD, hex2bin($key), OPENSSL_RAW_DATA, hex2bin($iv));
        if ($result === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return $result;
    }

    /**
     * Decrypt encrypted content using AES-256-CBC
     *
     * @param string $encrypted Encrypted binary string
     * @param string $key       64-char hex key
     * @param string $iv        32-char hex IV
     * @return string           Decrypted content
     * @throws \RuntimeException on failure
     */
    public static function decrypt(string $encrypted, string $key, string $iv): string
    {
        $result = openssl_decrypt($encrypted, self::METHOD, hex2bin($key), OPENSSL_RAW_DATA, hex2bin($iv));
        if ($result === false) {
            throw new \RuntimeException('Decryption failed.');
        }
        return $result;
    }

    /**
     * Hash file content using SHA-256
     *
     * @param string $content
     * @return string 64-character hex hash
     */
    public static function sha256(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Encrypt and save file to disk
     *
     * @param string $destination Path to save encrypted file
     * @param string $content     Raw content to encrypt
     * @param string $key         64-char hex key
     * @param string $iv          32-char hex IV
     * @return bool True on success, false on failure
     * @throws \RuntimeException on encryption failure
     */
    public static function saveEncrypted(string $destination, string $content, string $key, string $iv): bool
    {
        $encrypted = self::encrypt($content, $key, $iv);
        $result = @file_put_contents($destination, $encrypted);
        return ($result !== false);
    }

    /**
     * Read and decrypt a file from disk
     *
     * @param string $path Path to encrypted file
     * @param string $key  64-char hex key
     * @param string $iv   32-char hex IV
     * @return string      Decrypted content
     * @throws \RuntimeException on read or decryption failure
     */
    public static function readDecrypted(string $path, string $key, string $iv): string
    {
        $encrypted = @file_get_contents($path);
        if ($encrypted === false) {
            throw new \RuntimeException('Failed to read encrypted file.');
        }
        return self::decrypt($encrypted, $key, $iv);
    }

    /**
     * Securely delete a file by overwriting with random data before unlinking
     *
     * @param string $path Path to file
     * @return bool True on success, false on failure
     */
    public static function deleteSecurely(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $length = filesize($path);
        $handle = fopen($path, 'r+');

        if ($handle === false) {
            return false;
        }

        // Overwrite with random data
        fwrite($handle, random_bytes($length));
        fflush($handle); // Ensure data is written to disk
        fclose($handle);

        return unlink($path);
    }
}
