<?php

namespace Tests\Unit\Service;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Service\EncryptionService;
use Mockery;

/**
 * Unit tests for EncryptionService
 * 
 * @covers \PrestaShop\Module\Pskyc\Service\EncryptionService
 */
class EncryptionServiceTest extends BaseTestCase
{
    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = new EncryptionService();
    }

    public function testGenerateIV(): void
    {
        $iv = $this->encryptionService->generateIV();
        
        $this->assertIsString($iv);
        $this->assertEquals(24, strlen($iv)); // Base64 encoded 16 bytes = 24 chars (with padding)
        
        // Ensure IV is unique each time
        $iv2 = $this->encryptionService->generateIV();
        $this->assertNotEquals($iv, $iv2);
    }

    public function testEncryptAndDecrypt(): void
    {
        $testData = 'This is test data for encryption';
        $iv = $this->encryptionService->generateIV();
        
        // Test encryption
        $encrypted = $this->encryptionService->encrypt($testData, $iv);
        $this->assertIsString($encrypted);
        $this->assertNotEquals($testData, $encrypted);
        
        // Test decryption
        $decrypted = $this->encryptionService->decrypt($encrypted, $iv);
        $this->assertEquals($testData, $decrypted);
    }

    public function testEncryptFile(): void
    {
        // Create a temporary file
        $sourceFile = tempnam(sys_get_temp_dir(), 'test_encrypt_');
        $testContent = 'This is test file content for encryption';
        file_put_contents($sourceFile, $testContent);
        
        $destinationFile = tempnam(sys_get_temp_dir(), 'test_encrypted_');
        
        // Test file encryption
        $result = $this->encryptionService->encryptFile($sourceFile, $destinationFile);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('iv', $result);
        $this->assertArrayHasKey('sha256', $result);
        $this->assertEquals(hash('sha256', $testContent), $result['sha256']);
        $this->assertTrue(file_exists($destinationFile));
        
        // Verify encrypted file is different from original
        $encryptedContent = file_get_contents($destinationFile);
        $this->assertNotEquals($testContent, $encryptedContent);
        
        // Cleanup
        unlink($sourceFile);
        unlink($destinationFile);
    }

    public function testDecryptFile(): void
    {
        // Create and encrypt a test file first
        $sourceFile = tempnam(sys_get_temp_dir(), 'test_source_');
        $testContent = 'This is test file content for decryption';
        file_put_contents($sourceFile, $testContent);
        
        $encryptedFile = tempnam(sys_get_temp_dir(), 'test_encrypted_');
        $encryptionResult = $this->encryptionService->encryptFile($sourceFile, $encryptedFile);
        
        // Test file decryption
        $decrypted = $this->encryptionService->decryptFile($encryptedFile, $encryptionResult['iv']);
        
        $this->assertEquals($testContent, $decrypted);
        
        // Cleanup
        unlink($sourceFile);
        unlink($encryptedFile);
    }

    public function testVerifyIntegrity(): void
    {
        $testData = 'Test data for integrity check';
        $correctSha256 = hash('sha256', $testData);
        $incorrectSha256 = hash('sha256', 'Different data');
        
        $this->assertTrue($this->encryptionService->verifyIntegrity($testData, $correctSha256));
        $this->assertFalse($this->encryptionService->verifyIntegrity($testData, $incorrectSha256));
    }

    public function testSecureDelete(): void
    {
        // Create a test file
        $testFile = tempnam(sys_get_temp_dir(), 'test_delete_');
        file_put_contents($testFile, 'Test content to be deleted');
        
        $this->assertTrue(file_exists($testFile));
        
        // Test secure deletion
        $result = $this->encryptionService->secureDelete($testFile);
        
        $this->assertTrue($result);
        $this->assertFalse(file_exists($testFile));
    }

    public function testSecureDeleteNonExistentFile(): void
    {
        $nonExistentFile = '/tmp/non_existent_file_' . uniqid();
        
        // Should return true for non-existent files
        $result = $this->encryptionService->secureDelete($nonExistentFile);
        $this->assertTrue($result);
    }

    public function testEncryptFileWithNonExistentSource(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source file does not exist');
        
        $nonExistentFile = '/tmp/non_existent_' . uniqid();
        $destinationFile = tempnam(sys_get_temp_dir(), 'test_dest_');
        
        $this->encryptionService->encryptFile($nonExistentFile, $destinationFile);
    }

    public function testDecryptFileWithNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted file does not exist');
        
        $nonExistentFile = '/tmp/non_existent_' . uniqid();
        $iv = $this->encryptionService->generateIV();
        
        $this->encryptionService->decryptFile($nonExistentFile, $iv);
    }
}