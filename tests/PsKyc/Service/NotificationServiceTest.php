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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationServiceTest extends MockeryTestCase
{
    /** @var TranslatorInterface */
    private $translatorMock;

    /** @var RouterInterface */
    private $routerMock;

    /** @var EngineInterface */
    private $templatingMock;

    /** @var NotificationService */
    private $service;

    protected function setUp(): void
    {
        // Ensure mock classes are loaded before using them
        if (!class_exists('Context')) {
            require_once __DIR__ . '/../MockProxy.php';
        }

        // Set up mocks for PrestaShop classes using proper mock proxy system
        $contextMock = \Mockery::mock();
        $configMock = \Mockery::mock();
        $loggerMock = \Mockery::mock();
        $dbMock = \Mockery::mock();
        $mailMock = \Mockery::mock();
        $languageMock = \Mockery::mock();

        // Create shop and link mocks
        $shopMock = \Mockery::mock();
        $linkMock = \Mockery::mock();

        $languageMock->id = 1;

        // Set up the context mock with shop and link properties
        $contextMock->shop = $shopMock;
        $contextMock->link = $linkMock;
        $contextMock->language = $languageMock;

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
        $this->routerMock = \Mockery::mock(RouterInterface::class);
        $this->templatingMock = \Mockery::mock(EngineInterface::class);

        // Configure default translator behavior
        $this->translatorMock->shouldReceive('trans')
            ->andReturn('Translated text')
            ->byDefault();

        $this->service = new NotificationService($this->translatorMock, $this->routerMock, $this->templatingMock);
    }

    public function testConstructorWithTemplating()
    {
        $service = new NotificationService($this->translatorMock, $this->routerMock, $this->templatingMock);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testConstructorWithoutTemplating()
    {
        $service = new NotificationService($this->translatorMock, $this->routerMock);
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
        ];

        // Reset mock expectations and make Configuration throw exception on PS_LANG_DEFAULT
        \Configuration::shouldReceive('get')
            ->with('PS_LANG_DEFAULT')
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

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template for this specific test to verify variable replacement
        \Mail::setMockTemplateContent(
            'Hello {firstname} {lastname}! We received {document_count} documents for verification #{verification_id} on {upload_date}. Shop: {shop_name}',
            'Text version: Hello {firstname} {lastname}! We received {document_count} documents for verification #{verification_id} on {upload_date}. Shop: {shop_name}'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectation for the subject
        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Documents Received - Verification #%id%', ['%id%' => 1], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Documents Received - Verification #1');

        // Set up context link mock for verification URL
        $linkMock = \Mockery::mock();
        $linkMock->shouldReceive('getModuleLink')
            ->with('pskyc', 'verify', [], true)
            ->andReturn('https://example.com/module/pskyc/verify');

        $contextMock = \Mockery::mock();
        $contextMock->link = $linkMock;
        \Context::shouldReceive('getContext')->andReturn($contextMock);

        $result = $this->service->sendDocumentUploadConfirmation($verification, $customer, $documents);

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that the template variables were correctly replaced
        $this->assertEquals('document_upload_confirmation', $processedContent['template']);
        $this->assertEquals('KYC Documents Received - Verification #1', $processedContent['subject']);
        $this->assertEquals('john.doe@example.com', $processedContent['recipient']);

        // Check that all template variables were properly replaced in HTML content
        $expectedHtmlContent = 'Hello John Doe! We received 2 documents for verification #1 on 2025-01-01 10:00:00. Shop: Test Shop';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'Text version: Hello John Doe! We received 2 documents for verification #1 on 2025-01-01 10:00:00. Shop: Test Shop';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('John', $templateVars['{firstname}']);
        $this->assertEquals('Doe', $templateVars['{lastname}']);
        $this->assertEquals(1, $templateVars['{verification_id}']);
        $this->assertEquals(2, $templateVars['{document_count}']);
        $this->assertEquals('2025-01-01 10:00:00', $templateVars['{upload_date}']);
        $this->assertStringContainsString('https://example.com/', $templateVars['{verification_status_url}']);
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
        ];

        $documents = [];

        // Make Configuration throw exception
        \Configuration::shouldReceive('get')
            ->with('PS_LANG_DEFAULT')
            ->andThrow(new \Exception('Test exception'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC upload confirmation error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendDocumentUploadConfirmation($verification, $customer, $documents);

        $this->assertFalse($result);
    }

    public function testSendAdminNotificationSuccessVerbose()
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

        // Set up all expectations with debugging
        $this->routerMock->shouldReceive('generate')
            ->withArgs(function ($route, $params) {
                echo "Router called with route: $route, params: " . json_encode($params) . "\n";

                return $route === 'ps_pskyc_verification_view' && $params['verificationId'] === 1;
            })
            ->andReturn('https://example.com/admin/kyc/verification/1')
            ->once();

        $this->translatorMock->shouldReceive('trans')
            ->withArgs(function ($key, $params, $domain) {
                echo "Translator called with key: $key, params: " . json_encode($params) . ", domain: $domain\n";

                return true;
            })
            ->andReturn('New KYC Verification Request - #1')
            ->once();

        // Mock Mail::Send with debugging
        \Mail::shouldReceive('Send')
            ->withArgs(function (...$args) {
                echo 'Mail::Send called with args: ' . json_encode($args[0] ?? 'unknown') . "\n";

                return true;
            })
            ->andReturn(true)
            ->once();

        $this->setupContextExpectations();
        $this->setupAdminEmailsExpectations();

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
        ];

        // Make Translator throw an exception
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with('KYC Verification Expiry Warning - #%id%', ['%id%' => 1], 'Modules.Pskyc.Shop')
            ->andThrow(new \Exception('Translation service failed'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC expiry warning error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendExpiryWarning($verification, $customer, 30);

        $this->assertFalse($result);
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

    public function testSendThemeEmailWithZeroLangID()
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
            0,
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
                $txt = $mailsPath . $lang . '/' . $template . '.txt';
                $html = $mailsPath . $lang . '/' . $template . '.html';
                $this->assertFileExists($txt, "Missing fallback text template $txt");
                $this->assertFileExists($html, "Missing fallback html template $html");
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

    public function testSendStatusChangeNotificationTemplateVariables()
    {
        $verification = [
            'id_kyc_verification' => 123,
            'status' => 'approved',
            'date_submitted' => '2025-01-01 10:00:00',
            'admin_note' => 'All documents verified successfully',
        ];

        $customer = [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane.smith@example.com',
            'id_lang' => 1,
        ];

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test all variables used in status notifications
        \Mail::setMockTemplateContent(
            'Dear {firstname} {lastname}, your KYC verification #{verification_id} status is now: {status_label}. Admin note: {status_message}. Submitted on: {date_submitted}.',
            'TXT: Dear {firstname} {lastname}, verification #{verification_id} is {status_label}. Note: {status_message}. Date: {date_submitted}.'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectations for the status and subject
        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('Approved');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that the template variables were correctly replaced
        $this->assertEquals('verification_status', $processedContent['template']);
        $this->assertEquals('KYC Verification Approved', $processedContent['subject']);
        $this->assertEquals('jane.smith@example.com', $processedContent['recipient']);

        // Check that all template variables were properly replaced in HTML content
        $expectedHtmlContent = 'Dear Jane Smith, your KYC verification #123 status is now: Approved. Admin note: All documents verified successfully. Submitted on: 2025-01-01 10:00:00.';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'TXT: Dear Jane Smith, verification #123 is Approved. Note: All documents verified successfully. Date: 2025-01-01 10:00:00.';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Jane', $templateVars['{firstname}']);
        $this->assertEquals('Smith', $templateVars['{lastname}']);
        $this->assertEquals(123, $templateVars['{verification_id}']);
        $this->assertEquals('Approved', $templateVars['{status_label}']);
        $this->assertEquals('All documents verified successfully', $templateVars['{status_message}']);
        $this->assertEquals('2025-01-01 10:00:00', $templateVars['{date_submitted}']);
    }

    public function testSendDocumentUploadConfirmationTemplateVariables()
    {
        $verification = [
            'id_kyc_verification' => 456,
            'date_submitted' => '2025-01-15 14:30:00',
        ];

        $customer = [
            'firstname' => 'Alice',
            'lastname' => 'Johnson',
            'email' => 'alice.johnson@example.com',
            'id_lang' => 2,
        ];

        $documents = [
            ['id' => 10, 'filename' => 'passport.pdf'],
            ['id' => 11, 'filename' => 'utility_bill.jpg'],
            ['id' => 12, 'filename' => 'bank_statement.pdf'],
        ];

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test all variables used in document upload confirmation
        \Mail::setMockTemplateContent(
            'Hello {firstname} {lastname}! We received {document_count} documents for verification #{verification_id} on {upload_date}. Status URL: {verification_status_url}',
            'TXT: Hello {firstname} {lastname}! {document_count} docs received for #{verification_id} on {upload_date}. URL: {verification_status_url}'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectation for the subject
        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Documents Received - Verification #%id%', ['%id%' => 456], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Documents Received - Verification #456');

        // Set up context link mock for verification URL
        $linkMock = \Mockery::mock();
        $linkMock->shouldReceive('getModuleLink')
            ->with('pskyc', 'verify', [], true)
            ->andReturn('https://example.com/module/pskyc/verify');

        $contextMock = \Mockery::mock();
        $contextMock->link = $linkMock;
        \Context::shouldReceive('getContext')->andReturn($contextMock);

        $result = $this->service->sendDocumentUploadConfirmation($verification, $customer, $documents);

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that the template variables were correctly replaced
        $this->assertEquals('document_upload_confirmation', $processedContent['template']);
        $this->assertEquals('KYC Documents Received - Verification #456', $processedContent['subject']);
        $this->assertEquals('alice.johnson@example.com', $processedContent['recipient']);

        // Check that all template variables were properly replaced in HTML content
        $expectedHtmlContent = 'Hello Alice Johnson! We received 3 documents for verification #456 on 2025-01-15 14:30:00. Status URL: https://example.com/';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'TXT: Hello Alice Johnson! 3 docs received for #456 on 2025-01-15 14:30:00. URL: https://example.com/';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Alice', $templateVars['{firstname}']);
        $this->assertEquals('Johnson', $templateVars['{lastname}']);
        $this->assertEquals(456, $templateVars['{verification_id}']);
        $this->assertEquals(3, $templateVars['{document_count}']);
        $this->assertEquals('2025-01-15 14:30:00', $templateVars['{upload_date}']);
        $this->assertStringContainsString('https://example.com/', $templateVars['{verification_status_url}']);
    }

    public function testSendAdminNotificationTemplateVariables()
    {
        $verification = [
            'id_kyc_verification' => 789,
            'date_submitted' => '2025-01-20 09:15:00',
            'document_count' => 2,
        ];

        $customer = [
            'firstname' => 'Bob',
            'lastname' => 'Wilson',
            'email' => 'bob.wilson@example.com',
            'id_customer' => 42,
        ];

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test all variables used in admin notifications
        \Mail::setMockTemplateContent(
            'New KYC request from {customer_name} ({customer_email}) - Customer ID: {customer_id}. Verification #{verification_id} has {document_count} documents. Review at: {admin_verification_url}',
            'TXT: KYC from {customer_name} ({customer_email}) ID:{customer_id}. #{verification_id} with {document_count} docs. URL: {admin_verification_url}'
        );

        $this->setupContextExpectations();
        $this->setupAdminEmailsExpectations();
        $this->setupMailExpectations(true);

        // Set up router expectation for admin verification URL
        $this->routerMock->shouldReceive('generate')
            ->with('ps_pskyc_verification_view', ['verificationId' => 789])
            ->andReturn('https://example.com/admin/kyc/verification/789')
            ->once();

        // Set up translator expectation for the subject
        $this->translatorMock->shouldReceive('trans')
            ->with('New KYC Verification Request - #%id%', ['%id%' => 789], 'Modules.Pskyc.Admin')
            ->andReturn('New KYC Verification Request - #789');

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that the template variables were correctly replaced
        $this->assertEquals('admin_new_verification', $processedContent['template']);
        $this->assertEquals('New KYC Verification Request - #789', $processedContent['subject']);
        $this->assertEquals('admin@example.com', $processedContent['recipient']);

        // Check that all template variables were properly replaced in HTML content
        $expectedHtmlContent = 'New KYC request from Bob Wilson (bob.wilson@example.com) - Customer ID: 42. Verification #789 has 2 documents. Review at: https://example.com/admin/kyc/verification/789';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'TXT: KYC from Bob Wilson (bob.wilson@example.com) ID:42. #789 with 2 docs. URL: https://example.com/admin/kyc/verification/789';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Bob Wilson', $templateVars['{customer_name}']);
        $this->assertEquals('bob.wilson@example.com', $templateVars['{customer_email}']);
        $this->assertEquals(42, $templateVars['{customer_id}']);
        $this->assertEquals(789, $templateVars['{verification_id}']);
        $this->assertEquals(2, $templateVars['{document_count}']);
        $this->assertEquals('https://example.com/admin/kyc/verification/789', $templateVars['{admin_verification_url}']);
    }

    public function testSendExpiryWarningTemplateVariables()
    {
        $verification = [
            'id_kyc_verification' => 999,
            'date_expiry' => '2025-02-15 23:59:59',
        ];

        $customer = [
            'firstname' => 'Carol',
            'lastname' => 'Davis',
            'email' => 'carol.davis@example.com',
            'id_lang' => 1,
        ];

        $daysUntilExpiry = 7;

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test all variables used in expiry warnings
        \Mail::setMockTemplateContent(
            'Dear {firstname} {lastname}, your KYC verification #{verification_id} expires in {days_remaining} days on {expiry_date}. Renew at: {renewal_url}',
            'TXT: {firstname} {lastname}, verification #{verification_id} expires in {days_remaining} days ({expiry_date}). Renew: {renewal_url}'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectation for the subject
        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Expiry Warning - #%id%', ['%id%' => 999], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Expiry Warning - #999');

        $result = $this->service->sendExpiryWarning($verification, $customer, $daysUntilExpiry);

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that the template variables were correctly replaced
        $this->assertEquals('verification_expiry_warning', $processedContent['template']);
        $this->assertEquals('KYC Verification Expiry Warning - #999', $processedContent['subject']);
        $this->assertEquals('carol.davis@example.com', $processedContent['recipient']);

        // Check that all template variables were properly replaced in HTML content
        $expectedHtmlContent = 'Dear Carol Davis, your KYC verification #999 expires in 7 days on 2025-02-15 23:59:59. Renew at: https://example.com/';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'TXT: Carol Davis, verification #999 expires in 7 days (2025-02-15 23:59:59). Renew: https://example.com/';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Carol', $templateVars['{firstname}']);
        $this->assertEquals('Davis', $templateVars['{lastname}']);
        $this->assertEquals(999, $templateVars['{verification_id}']);
        $this->assertEquals(7, $templateVars['{days_remaining}']);
        $this->assertEquals('2025-02-15 23:59:59', $templateVars['{expiry_date}']);
        $this->assertStringContainsString('https://example.com/', $templateVars['{renewal_url}']);
    }

    public function testSendStatusChangeNotificationWithAllStatusTypes()
    {
        $baseVerification = [
            'id_kyc_verification' => 100,
            'date_submitted' => '2025-01-01 10:00:00',
            'admin_note' => 'Test admin note',
        ];

        $customer = [
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test.user@example.com',
            'id_lang' => 1,
        ];

        $statusTestCases = [
            'approved' => ['Approved', 'KYC Verification Approved'],
            'rejected' => ['Rejected', 'KYC Verification Rejected'],
            'pending' => ['Pending Review', 'KYC Verification Status Update'],
            'expired' => ['Expired', 'KYC Verification Expired'],
            'requested_more_info' => ['More Information Required', 'KYC Verification Status Update'],
        ];

        foreach ($statusTestCases as $status => [$expectedLabel, $expectedSubject]) {
            // Reset Mail mock state for each test
            \Mail::resetMockState();

            // Set a custom template to test status-specific variables
            \Mail::setMockTemplateContent(
                'Dear {firstname} {lastname}, verification #{verification_id} status: {status_label}. Note: {status_message}. Submitted: {date_submitted}',
                'TXT: {firstname} {lastname}, #{verification_id}: {status_label}. Note: {status_message}. Date: {date_submitted}'
            );

            $verification = array_merge($baseVerification, ['status' => $status]);

            $this->setupContextExpectations();
            $this->setupMailExpectations(true);

            // Set up translator expectations for status label
            $this->translatorMock->shouldReceive('trans')
                ->with($expectedLabel === 'More Information Required' ? 'More Information Required' : $expectedLabel, [], 'Modules.Pskyc.Shop')
                ->andReturn($expectedLabel);

            // Set up translator expectation for the subject
            $this->translatorMock->shouldReceive('trans')
                ->with($expectedSubject, [], 'Modules.Pskyc.Shop')
                ->andReturn($expectedSubject);

            $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

            $this->assertTrue($result, "Failed for status: $status");

            // Get the processed template content from the Mail mock
            $processedContent = \Mail::getLastProcessedContent();

            // Verify that the template variables were correctly replaced
            $this->assertEquals('verification_status', $processedContent['template']);
            $this->assertEquals($expectedSubject, $processedContent['subject']);
            $this->assertEquals('test.user@example.com', $processedContent['recipient']);

            // Verify specific template variables were passed correctly
            $templateVars = $processedContent['templateVars'];
            $this->assertEquals('Test', $templateVars['{firstname}']);
            $this->assertEquals('User', $templateVars['{lastname}']);
            $this->assertEquals(100, $templateVars['{verification_id}']);
            $this->assertEquals($expectedLabel, $templateVars['{status_label}']);
            $this->assertEquals('Test admin note', $templateVars['{status_message}']);
            $this->assertEquals('2025-01-01 10:00:00', $templateVars['{date_submitted}']);
        }
    }

    public function testTemplateVariablesWithEmptyValues()
    {
        $verification = [
            'id_kyc_verification' => 101,
            'status' => 'pending',
            'date_submitted' => '2025-01-01 10:00:00',
            // admin_note is missing to test empty values
        ];

        $customer = [
            'firstname' => 'Empty',
            'lastname' => 'Test',
            'email' => 'empty.test@example.com',
            'id_lang' => 1,
        ];

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test empty/missing values
        \Mail::setMockTemplateContent(
            'Status: {status_label}, Note: "{status_message}"',
            'TXT: Status: {status_label}, Note: "{status_message}"'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectations
        $this->translatorMock->shouldReceive('trans')
            ->with('Pending Review', [], 'Modules.Pskyc.Shop')
            ->andReturn('Pending Review');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Status Update', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Status Update');

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that empty admin_note is handled correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Pending Review', $templateVars['{status_label}']);
        $this->assertEquals('', $templateVars['{status_message}']); // Should be empty string when admin_note is missing

        // Check that empty values are properly replaced in content
        $expectedHtmlContent = 'Status: Pending Review, Note: ""';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);
    }

    public function testTemplateVariablesWithSpecialCharacters()
    {
        $verification = [
            'id_kyc_verification' => 102,
            'status' => 'approved',
            'date_submitted' => '2025-01-01 10:00:00',
            'admin_note' => 'Documents verified with special chars: àáâãäåæçèéêë & symbols!',
        ];

        $customer = [
            'firstname' => 'José',
            'lastname' => 'García-López',
            'email' => 'jose.garcia@example.com',
            'id_lang' => 1,
        ];

        // Reset Mail mock state for clean test
        \Mail::resetMockState();

        // Set a custom template to test special characters
        \Mail::setMockTemplateContent(
            'Customer: {firstname} {lastname}, Note: {status_message}',
            'TXT: Customer: {firstname} {lastname}, Note: {status_message}'
        );

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        // Set up translator expectations
        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('Approved');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $result = $this->service->sendStatusChangeNotification($verification, $customer, 'pending');

        $this->assertTrue($result);

        // Get the processed template content from the Mail mock
        $processedContent = \Mail::getLastProcessedContent();

        // Verify that special characters are handled correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('José', $templateVars['{firstname}']);
        $this->assertEquals('García-López', $templateVars['{lastname}']);
        $this->assertEquals('Documents verified with special chars: àáâãäåæçèéêë & symbols!', $templateVars['{status_message}']);

        // Check that special characters are properly replaced in content
        $expectedHtmlContent = 'Customer: José García-López, Note: Documents verified with special chars: àáâãäåæçèéêë & symbols!';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);
    }
}
