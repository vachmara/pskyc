<?php

namespace Tests\Unit\Service;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use Symfony\Component\Translation\TranslatorInterface;
use Mockery;

/**
 * Unit tests for NotificationService
 * 
 * @covers \PrestaShop\Module\Pskyc\Service\NotificationService
 */
class NotificationServiceTest extends BaseTestCase
{
    private NotificationService $notificationService;
    private $mockTranslator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTranslator = Mockery::mock(TranslatorInterface::class);
        $this->notificationService = new NotificationService($this->mockTranslator);
    }

    public function testSendStatusChangeNotificationSuccess(): void
    {
        $verification = $this->createMockVerification(['status' => 'approved']);
        $customer = $this->createMockCustomer();
        $previousStatus = 'pending';
        
        // Mock translator
        $this->mockTranslator
            ->shouldReceive('trans')
            ->andReturn('KYC Verification Approved');
        
        $result = $this->notificationService->sendStatusChangeNotification($verification, $customer, $previousStatus);
        
        $this->assertTrue($result);
    }

    public function testSendDocumentUploadConfirmation(): void
    {
        $verification = $this->createMockVerification();
        $customer = $this->createMockCustomer();
        $documents = [
            $this->createMockDocument(),
            $this->createMockDocument(['id_kyc_document' => 2])
        ];
        
        // Mock translator
        $this->mockTranslator
            ->shouldReceive('trans')
            ->andReturn('KYC Documents Received');
        
        $result = $this->notificationService->sendDocumentUploadConfirmation($verification, $customer, $documents);
        
        $this->assertTrue($result);
    }

    public function testSendAdminNotification(): void
    {
        $verification = $this->createMockVerification();
        $customer = $this->createMockCustomer();
        
        // Mock translator
        $this->mockTranslator
            ->shouldReceive('trans')
            ->andReturn('New KYC Verification Request');
        
        $result = $this->notificationService->sendAdminNotification($verification, $customer);
        
        $this->assertTrue($result);
    }

    public function testSendExpiryWarning(): void
    {
        $verification = $this->createMockVerification([
            'status' => 'approved',
            'date_expiry' => '2025-02-01 10:00:00'
        ]);
        $customer = $this->createMockCustomer();
        $daysUntilExpiry = 30;
        
        // Mock translator
        $this->mockTranslator
            ->shouldReceive('trans')
            ->andReturn('KYC Verification Expiring Soon');
        
        $result = $this->notificationService->sendExpiryWarning($verification, $customer, $daysUntilExpiry);
        
        $this->assertTrue($result);
    }

    public function testCreateInAppNotification(): void
    {
        $customerId = 123;
        $message = 'Your KYC verification has been approved';
        $type = 'success';
        
        $result = $this->notificationService->createInAppNotification($customerId, $message, $type);
        
        $this->assertTrue($result);
    }

    public function testGetEmailTemplateForStatus(): void
    {
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('getEmailTemplate');
        $method->setAccessible(true);
        
        $this->assertEquals('verification_approved', $method->invoke($this->notificationService, 'approved'));
        $this->assertEquals('verification_rejected', $method->invoke($this->notificationService, 'rejected'));
        $this->assertEquals('verification_under_review', $method->invoke($this->notificationService, 'under_review'));
        $this->assertEquals('verification_status_change', $method->invoke($this->notificationService, 'unknown_status'));
    }

    public function testGetEmailSubjectForStatus(): void
    {
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('getEmailSubject');
        $method->setAccessible(true);
        
        // Mock translator for all calls
        $this->mockTranslator
            ->shouldReceive('trans')
            ->andReturnUsing(function($key) {
                $subjects = [
                    'KYC Verification Approved' => 'KYC Verification Approved',
                    'KYC Verification Rejected' => 'KYC Verification Rejected',
                    'KYC Verification Under Review' => 'KYC Verification Under Review',
                    'KYC Verification Status Update' => 'KYC Verification Status Update'
                ];
                return $subjects[$key] ?? $key;
            });
        
        $approvedSubject = $method->invoke($this->notificationService, 'approved');
        $rejectedSubject = $method->invoke($this->notificationService, 'rejected');
        $reviewSubject = $method->invoke($this->notificationService, 'under_review');
        $defaultSubject = $method->invoke($this->notificationService, 'unknown_status');
        
        $this->assertIsString($approvedSubject);
        $this->assertIsString($rejectedSubject);
        $this->assertIsString($reviewSubject);
        $this->assertIsString($defaultSubject);
    }

    public function testGetAdminEmails(): void
    {
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);
        
        // Set additional admin emails
        \Configuration::updateValue('PSKYC_ADMIN_EMAILS', 'admin1@test.com,admin2@test.com');
        
        $emails = $method->invoke($this->notificationService);
        
        $this->assertIsArray($emails);
        $this->assertGreaterThan(0, count($emails));
        
        foreach ($emails as $email) {
            $this->assertArrayHasKey('email', $email);
            $this->assertArrayHasKey('name', $email);
            $this->assertTrue(\Validate::isEmail($email['email']));
        }
    }

    public function testSendBulkNotification(): void
    {
        $recipients = [
            ['email' => 'user1@test.com', 'name' => 'User 1'],
            ['email' => 'user2@test.com', 'name' => 'User 2']
        ];
        $subject = 'Test Notification';
        $templateVars = ['message' => 'Test message'];
        $template = 'test_template';
        
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendBulkNotification');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->notificationService, $recipients, $subject, $templateVars, $template);
        
        $this->assertTrue($result);
    }
}