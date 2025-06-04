<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace Tests\PsKyc\Service;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationServiceTest extends MockeryTestCase
{
    /** @var TranslatorInterface */
    private $translatorMock;

    /** @var EngineInterface */
    private $templatingMock;

    /** @var NotificationService */
    private $service;

    protected function setUp(): void
    {
        // Set up mocks for PrestaShop classes using proper mock proxy system
        $contextMock = \Mockery::mock();
        $configMock = \Mockery::mock();
        $loggerMock = \Mockery::mock();
        $dbMock = \Mockery::mock();
        $mailMock = \Mockery::mock();

        // Create shop and link mocks
        $shopMock = \Mockery::mock();
        $linkMock = \Mockery::mock();

        // Set up the context mock with shop and link properties
        $contextMock->shop = $shopMock;
        $contextMock->link = $linkMock;

        \Context::setStaticExpectations($contextMock);
        \Configuration::setStaticExpectations($configMock);
        \PrestaShopLogger::setStaticExpectations($loggerMock);
        \Db::setStaticExpectations($dbMock);
        \Mail::setStaticExpectations($mailMock);

        // Configure default expectations
        $loggerMock->shouldReceive('addLog')->byDefault();
        $configMock->shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andReturn('Test Shop')
            ->byDefault();
        $configMock->shouldReceive('get')
            ->with('PS_LANG_DEFAULT')
            ->andReturn(1)
            ->byDefault();

        // Configure shop and link mocks
        $shopMock->shouldReceive('getBaseURL')
            ->with(true)
            ->andReturn('https://example.com/')
            ->byDefault();

        $linkMock->shouldReceive('getAdminLink')
            ->with('AdminModules')
            ->andReturn('https://example.com/admin/modules')
            ->byDefault();

        // Set up service dependencies
        $this->translatorMock = \Mockery::mock(TranslatorInterface::class);
        $this->templatingMock = \Mockery::mock(EngineInterface::class);

        // Configure default translator behavior
        $this->translatorMock->shouldReceive('trans')
            ->andReturn('Translated text')
            ->byDefault();

        $this->service = new NotificationService($this->translatorMock, $this->templatingMock);
    }

    public function testConstructorWithTemplating()
    {
        $service = new NotificationService($this->translatorMock, $this->templatingMock);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testConstructorWithoutTemplating()
    {
        $service = new NotificationService($this->translatorMock);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testSendStatusChangeNotificationSuccess()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'status' => 'approved',
            'date_submitted' => '2025-01-01 10:00:00',
            'date_validated' => '2025-01-02 10:00:00',
            'date_expiry' => '2025-12-31 23:59:59',
            'admin_note' => 'Test note',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertTrue($result);
    }

    public function testSendStatusChangeNotificationFailure()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'status' => 'approved',
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        $this->setupContextExpectations();
        $this->setupMailExpectations(false);

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertFalse($result);
    }

    public function testSendStatusChangeNotificationException()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'status' => 'approved',
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        // Reset mock expectations and make Configuration throw exception
        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andThrow(new \Exception('Test exception'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC notification error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertFalse($result);
    }

    public function testSendDocumentUploadConfirmationSuccess()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        $documents = [
            ['id' => 1, 'filename' => 'doc1.pdf'],
            ['id' => 2, 'filename' => 'doc2.pdf'],
        ];

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        $result = $this->service->sendDocumentUploadConfirmation($verification, $customer, $documents);

        $this->assertTrue($result);
    }

    public function testSendDocumentUploadConfirmationException()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        $documents = [];

        // Make Configuration throw exception
        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andThrow(new \Exception('Test exception'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC upload confirmation error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendDocumentUploadConfirmation($verification, $customer, $documents);

        $this->assertFalse($result);
    }

    public function testSendAdminNotificationSuccess()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_customer' => 1,
        ];

        $this->setupContextExpectations();
        $this->setupAdminEmailsExpectations();
        $this->setupMailExpectations(true);

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertTrue($result);
    }

    public function testSendAdminNotificationNoAdminEmails()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_customer' => 1,
        ];

        $this->setupContextExpectations();

        \Db::shouldReceive('getInstance')
            ->once()
            ->andReturnSelf();
        \Db::shouldReceive('executeS')
            ->once()
            ->andReturn([]);

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertFalse($result);
    }

    public function testSendAdminNotificationException()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_customer' => 1,
        ];

        // First, set up getAdminEmails to return valid admin emails
        $adminEmails = [
            ['email' => 'admin@example.com', 'name' => 'Admin User'],
        ];

        \Db::shouldReceive('getInstance')
            ->once()
            ->andReturnSelf();
        \Db::shouldReceive('executeS')
            ->once()
            ->andReturn($adminEmails);

        // Then make the translator throw an exception when called
        $this->translatorMock->shouldReceive('trans')
            ->with('New KYC Verification Request - #%id%', ['%id%' => 1], 'Modules.Pskyc.Admin')
            ->andThrow(new \Exception('Translation service failed'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC admin notification error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertFalse($result);
    }

    public function testSendExpiryWarningSuccess()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_expiry' => '2025-12-31 23:59:59',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        $result = $this->service->sendExpiryWarning($verification, $customer, 30);

        $this->assertTrue($result);
    }

    public function testSendExpiryWarningException()
    {
        $verification = [
            'id_kyc_verification' => 1,
            'date_expiry' => '2025-12-31 23:59:59',
        ];

        $customer = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
        ];

        // Make Configuration throw exception
        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andThrow(new \Exception('Test exception'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC expiry warning error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendExpiryWarning($verification, $customer, 30);

        $this->assertFalse($result);
    }

    public function testSendBulkNotificationSuccess()
    {
        $recipients = [
            ['email' => 'user1@example.com', 'name' => 'User One'],
            ['email' => 'user2@example.com', 'name' => 'User Two'],
            ['email' => 'user3@example.com'],
        ];

        \Mail::shouldReceive('Send')
            ->times(3)
            ->andReturn(true);

        $result = $this->service->sendBulkNotification(
            $recipients,
            'test_template',
            'Test Subject',
            ['test' => 'data']
        );

        $this->assertEquals(3, $result['success_count']);
        $this->assertEquals(0, count($result['failed_emails']));
        $this->assertEquals(3, $result['total_sent']);
    }

    public function testSendBulkNotificationPartialFailure()
    {
        $recipients = [
            ['email' => 'user1@example.com', 'name' => 'User One'],
            ['email' => 'user2@example.com', 'name' => 'User Two'],
        ];

        \Mail::shouldReceive('Send')
            ->twice()
            ->andReturn(true, false);

        $result = $this->service->sendBulkNotification(
            $recipients,
            'test_template',
            'Test Subject',
            ['test' => 'data']
        );

        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(1, count($result['failed_emails']));
        $this->assertEquals('user2@example.com', $result['failed_emails'][0]);
        $this->assertEquals(2, $result['total_sent']);
    }

    public function testGetEmailSubjectForStatusApproved()
    {
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEmailSubjectForStatus');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, ['approved']);

        $this->assertEquals('KYC Verification Approved', $result);
    }

    public function testGetEmailSubjectForStatusRejected()
    {
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with('KYC Verification Rejected', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Rejected');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEmailSubjectForStatus');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, ['rejected']);

        $this->assertEquals('KYC Verification Rejected', $result);
    }

    public function testGetEmailSubjectForStatusExpired()
    {
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with('KYC Verification Expired', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Expired');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEmailSubjectForStatus');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, ['expired']);

        $this->assertEquals('KYC Verification Expired', $result);
    }

    public function testGetEmailSubjectForStatusDefault()
    {
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with('KYC Verification Status Update', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Status Update');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEmailSubjectForStatus');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, ['pending']);

        $this->assertEquals('KYC Verification Status Update', $result);
    }

    public function testGetAdminEmailsSuccess()
    {
        $adminEmails = [
            ['email' => 'admin1@example.com', 'name' => 'Admin One'],
            ['email' => 'admin2@example.com', 'name' => 'Admin Two'],
        ];

        \Db::shouldReceive('getInstance')
            ->once()
            ->andReturnSelf();
        \Db::shouldReceive('executeS')
            ->once()
            ->andReturn($adminEmails);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, []);

        $this->assertEquals(2, count($result));
        $this->assertEquals('admin1@example.com', $result[0]['email']);
        $this->assertEquals('Admin One', $result[0]['name']);
    }

    public function testGetAdminEmailsEmpty()
    {
        \Db::shouldReceive('getInstance')
            ->once()
            ->andReturnSelf();
        \Db::shouldReceive('executeS')
            ->once()
            ->andReturn([]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, []);

        $this->assertEquals([], $result);
    }

    public function testGetAdminEmailsException()
    {
        \Db::shouldReceive('getInstance')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/Error getting admin emails:/'), 3, null, 'Pskyc');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, []);

        $this->assertEquals([], $result);
    }

    public function testSendThemeEmailSuccess()
    {
        \Mail::shouldReceive('Send')
            ->once()
            ->andReturn(true);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sendThemeEmail');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, [
            'test_template',
            'Test Subject',
            ['test' => 'data'],
            'test@example.com',
            'Test User',
            1,
        ]);

        $this->assertTrue($result);
    }

    public function testSendThemeEmailEmptyRecipient()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sendThemeEmail');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, [
            'test_template',
            'Test Subject',
            ['test' => 'data'],
            '',
            'Test User',
            1,
        ]);

        $this->assertFalse($result);
    }

    public function testSendThemeEmailException()
    {
        \Mail::shouldReceive('Send')
            ->once()
            ->andThrow(new \Exception('Mail error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/Theme email error:/'), 3, null, 'Pskyc');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sendThemeEmail');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->service, [
            'test_template',
            'Test Subject',
            ['test' => 'data'],
            'test@example.com',
            'Test User',
            1,
        ]);

        $this->assertFalse($result);
    }

    public function testFallbackTemplatesExist()
    {
        $rootPath = dirname(__DIR__, 3);
        $mailsPath = $rootPath . '/mails/';

        $templates = [
            'verification_status',
            'document_upload_confirmation',
            'admin_new_verification',
            'verification_expiry_warning',
        ];

        foreach (['en', 'fr'] as $lang) {
            foreach ($templates as $template) {
                $file = $mailsPath . $lang . '/' . $template . '.txt';
                $this->assertFileExists($file, "Missing fallback text template $file");
            }
        }

        $layoutBase = $mailsPath . 'layouts/';
        foreach ($templates as $template) {
            $this->assertFileExists($layoutBase . $template . '.html.twig');
            foreach (['classic', 'modern'] as $theme) {
                $layout = $layoutBase . $theme . '/' . $template . '.html.twig';
                $this->assertFileExists($layout);
            }
        }
    }

    /**
     * Helper method to set up Context expectations
     */
    private function setupContextExpectations()
    {
        $shopMock = \Mockery::mock();
        $linkMock = \Mockery::mock();

        \Context::shouldReceive('getContext')
            ->andReturnSelf();

        $shopMock->shouldReceive('getBaseURL')
            ->with(true)
            ->andReturn('https://example.com/');

        $linkMock->shouldReceive('getAdminLink')
            ->with('AdminModules')
            ->andReturn('https://example.com/admin/modules');
    }

    /**
     * Helper method to set up admin emails expectations
     */
    private function setupAdminEmailsExpectations()
    {
        $adminEmails = [
            ['email' => 'admin@example.com', 'name' => 'Admin User'],
        ];

        \Db::shouldReceive('getInstance')
            ->once()
            ->andReturnSelf();
        \Db::shouldReceive('executeS')
            ->once()
            ->andReturn($adminEmails);
    }

    /**
     * Helper method to set up Mail expectations
     */
    private function setupMailExpectations($returnValue)
    {
        \Mail::shouldReceive('Send')
            ->andReturn($returnValue);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
