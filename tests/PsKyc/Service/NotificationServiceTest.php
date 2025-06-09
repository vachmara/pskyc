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
        $validateMock = \Mockery::mock();

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
        \Validate::setStaticExpectations($validateMock);

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
        $configMock->shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('shop@example.com')
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
            ->andReturn('translated text')
            ->byDefault();

        // Create service with only translator - no router needed
        $this->service = new NotificationService($this->translatorMock);
    }

    public function testConstructorWithTemplating()
    {
        $service = new NotificationService($this->translatorMock);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testConstructorWithoutTemplating()
    {
        $service = new NotificationService($this->translatorMock);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testConstructorWithoutRouter()
    {
        // Test front-end context without router
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

        // Set up Configuration expectations
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        // Set up Validate expectations
        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        // Set up simple context expectations (only shop base URL needed)
        $this->setupContextExpectations();

        // Set up translator expectations
        $this->translatorMock->shouldReceive('trans')
            ->with('New KYC Verification Request - #%id%', ['%id%' => 1], 'Modules.Pskyc.Admin')
            ->andReturn('New KYC Verification Request - #1')
            ->once();

        // Mock Mail::Send to return true
        \Mail::shouldReceive('Send')
            ->andReturn(true)
            ->once();

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertTrue($result);
    }

    public function testSendAdminNotificationAdminEmailsEmpty()
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

        // Mock Configuration::get for PSKYC_ADMIN_EMAILS to return empty
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('')
            ->once();

        $result = $this->service->sendAdminNotification($verification, $customer);
        $this->assertFalse($result, 'Expected false when admin emails are empty');
    }

    public function testSendAdminNotificationAdminEmailsThrowException()
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

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        // Set up Validate expectations
        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        // Mock translator to throw an exception on trans
        $this->translatorMock->shouldReceive('trans')
            ->once()
            ->with(
                'New KYC Verification Request - #%id%',
                ['%id%' => 1],
                'Modules.Pskyc.Admin'
            )
            ->andThrow(new \Exception('Translation service failed'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC admin notification error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendAdminNotification($verification, $customer);

        $this->assertFalse($result);
    }

    public function testGetStatusLabelDefault()
    {
        // Test the default case when status doesn't match any predefined values
        $this->translatorMock->shouldReceive('trans')
            ->with('Unknown', [], 'Modules.Pskyc.Shop')
            ->andReturn('Unknown')
            ->once();

        // Use reflection to access the private getStatusLabel method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getStatusLabel');
        $method->setAccessible(true);

        // Test with an unknown/invalid status
        $result = $method->invokeArgs($this->service, ['invalid_status']);

        $this->assertEquals('Unknown', $result);
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

        // Add missing AdminPskycVerification link for admin notifications with specific parameters
        $linkMock->shouldReceive('getAdminLink')
            ->with('AdminPskycVerification', true, \Mockery::any())
            ->andReturn('https://example.com/admin/kyc/verification/123');
    }

    /**
     * Helper method to set up admin emails expectations
     */
    private function setupAdminEmailsExpectations()
    {
        // Mock Configuration::get for PSKYC_ADMIN_EMAILS - return a valid email
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->byDefault();

        // Mock Configuration::get for PS_SHOP_EMAIL as fallback
        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('shop@example.com')
            ->byDefault();

        // Mock Configuration::get for PS_SHOP_NAME
        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andReturn('Test Shop')
            ->byDefault();

        // Mock Validate::isEmail to return true for our test emails
        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->byDefault();

        \Validate::shouldReceive('isEmail')
            ->with('shop@example.com')
            ->andReturn(true)
            ->byDefault();
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
            'Dear {firstname} {lastname}, your KYC verification #{verification_id} status is now: {status_label}. Submitted on: {date_submitted}.',
            'TXT: Dear {firstname} {lastname}, verification #{verification_id} is {status_label}. Date: {date_submitted}.'
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
        $expectedHtmlContent = 'Dear Jane Smith, your KYC verification #123 status is now: Approved. Submitted on: 2025-01-01 10:00:00.';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content
        $expectedTxtContent = 'TXT: Dear Jane Smith, verification #123 is Approved. Date: 2025-01-01 10:00:00.';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Jane', $templateVars['{firstname}']);
        $this->assertEquals('Smith', $templateVars['{lastname}']);
        $this->assertEquals(123, $templateVars['{verification_id}']);
        $this->assertEquals('Approved', $templateVars['{status_label}']);
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

        // Set up Configuration expectations
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        // Set up Validate expectations
        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        // Set up simple context expectations (only shop base URL needed)
        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

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

        // Check that all template variables were properly replaced in HTML content (now expects shop base URL)
        $expectedHtmlContent = 'New KYC request from Bob Wilson (bob.wilson@example.com) - Customer ID: 42. Verification #789 has 2 documents. Review at: https://example.com/';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);

        // Check that all template variables were properly replaced in TXT content (now expects shop base URL)
        $expectedTxtContent = 'TXT: KYC from Bob Wilson (bob.wilson@example.com) ID:42. #789 with 2 docs. URL: https://example.com/';
        $this->assertEquals($expectedTxtContent, $processedContent['txt']);

        // Verify specific template variables were passed correctly (admin_verification_url now uses shop base URL)
        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Bob Wilson', $templateVars['{customer_name}']);
        $this->assertEquals('bob.wilson@example.com', $templateVars['{customer_email}']);
        $this->assertEquals(42, $templateVars['{customer_id}']);
        $this->assertEquals(789, $templateVars['{verification_id}']);
        $this->assertEquals(2, $templateVars['{document_count}']);
        $this->assertEquals('https://example.com/', $templateVars['{admin_verification_url}']); // Updated to expect shop base URL
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
                'Dear {firstname} {lastname}, verification #{verification_id} status: {status_label}. Submitted: {date_submitted}',
                'TXT: {firstname} {lastname}, #{verification_id}: {status_label}. Date: {date_submitted}'
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
            'Status: {status_label}',
            'TXT: Status: {status_label}'
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

        // Check that empty values are properly replaced in content
        $expectedHtmlContent = 'Status: Pending Review';
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
            'Customer: {firstname} {lastname}',
            'TXT: Customer: {firstname} {lastname}'
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

        // Check that special characters are properly replaced in content
        $expectedHtmlContent = 'Customer: José García-López';
        $this->assertEquals($expectedHtmlContent, $processedContent['html']);
    }

    public function testGetAdminEmailsFallbackToShopEmail()
    {
        // Test the fallback path when PSKYC_ADMIN_EMAILS is empty
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('')
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('shop@example.com')
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andReturn('Test Shop')
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('shop@example.com', $result[0]['email']);
        $this->assertEquals('Test Shop', $result[0]['name']);
    }

    public function testGetAdminEmailsFallbackWhenShopEmailIsEmpty()
    {
        // Test the fallback path when both PSKYC_ADMIN_EMAILS and PS_SHOP_EMAIL are empty
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('')
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('')
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAdminEmailsFallbackWhenShopEmailIsNull()
    {
        // Test the fallback path when PSKYC_ADMIN_EMAILS is empty and PS_SHOP_EMAIL is null
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn(null)
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn(null)
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAdminEmailsWithValidConfigButInvalidEmails()
    {
        // Test when PSKYC_ADMIN_EMAILS contains invalid emails and fallback is needed
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('invalid-email, another-invalid')
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('valid@shop.com')
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andReturn('Test Shop')
            ->once();

        // Mock email validation to return false for invalid emails
        \Validate::shouldReceive('isEmail')
            ->with('invalid-email')
            ->andReturn(false)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('another-invalid')
            ->andReturn(false)
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('valid@shop.com', $result[0]['email']);
        $this->assertEquals('Test Shop', $result[0]['name']);
    }

    public function testGetAdminEmailsWithValidConfigEmails()
    {
        // Test when PSKYC_ADMIN_EMAILS contains valid emails (no fallback needed)
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin1@example.com, admin2@example.com')
            ->once();

        // Mock email validation to return true for valid emails
        \Validate::shouldReceive('isEmail')
            ->with('admin1@example.com')
            ->andReturn(true)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin2@example.com')
            ->andReturn(true)
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('admin1@example.com', $result[0]['email']);
        $this->assertEquals('Administrator', $result[0]['name']);
        $this->assertEquals('admin2@example.com', $result[1]['email']);
        $this->assertEquals('Administrator', $result[1]['name']);
    }

    public function testGetAdminEmailsWithMixedValidInvalidEmails()
    {
        // Test when PSKYC_ADMIN_EMAILS contains mix of valid and invalid emails
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('valid@example.com, invalid-email, another@valid.com')
            ->once();

        // Mock email validation
        \Validate::shouldReceive('isEmail')
            ->with('valid@example.com')
            ->andReturn(true)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('invalid-email')
            ->andReturn(false)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('another@valid.com')
            ->andReturn(true)
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Only valid emails should be included
        $this->assertEquals('valid@example.com', $result[0]['email']);
        $this->assertEquals('Administrator', $result[0]['name']);
        $this->assertEquals('another@valid.com', $result[1]['email']);
        $this->assertEquals('Administrator', $result[1]['name']);
    }

    public function testGetAdminEmailsExceptionHandling()
    {
        // Test exception handling in getAdminEmails method
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andThrow(new \Exception('Configuration error'))
            ->once();

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/Error getting admin emails:/'), 3, null, 'Pskyc');

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
    }

    public function testGetAdminEmailsExceptionHandlingWithEmptyShopEmail()
    {
        // Test exception handling when even the fallback shop email is empty
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andThrow(new \Exception('Configuration error'))
            ->once();

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/Error getting admin emails:/'), 3, null, 'Pskyc');

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAdminEmailsWithWhitespaceHandling()
    {
        // Test that the method properly handles emails with extra whitespace
        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('  admin1@example.com  ,   admin2@example.com   ')
            ->once();

        // Mock email validation - note that trim() should be applied
        \Validate::shouldReceive('isEmail')
            ->with('admin1@example.com')
            ->andReturn(true)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin2@example.com')
            ->andReturn(true)
            ->once();

        // Use reflection to access the private getAdminEmails method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAdminEmails');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('admin1@example.com', $result[0]['email']);
        $this->assertEquals('admin2@example.com', $result[1]['email']);
    }

    public function testSendAdminStatusChangeNotificationSuccess()
    {
        $verification = [
            'id_kyc_verification' => 5,
            'status' => 'approved',
            'admin_note' => 'ok',
        ];

        $customer = [
            'firstname' => 'Adam',
            'lastname' => 'West',
            'email' => 'adam.west@example.com',
            'id_customer' => 10,
        ];

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        $this->setupContextExpectations();
        \Mail::resetMockState();
        \Mail::setMockTemplateContent(
            'Verification #{verification_id} for {customer_name} ({customer_email}) was {status_label}: {status_message}. URL: {admin_verification_url}',
            'TXT: #{verification_id} {status_label} {status_message} {admin_verification_url}'
        );
        $this->setupMailExpectations(true);

        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('Approved');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertTrue($result);

        $processedContent = \Mail::getLastProcessedContent();
        $this->assertEquals('admin_verification_status', $processedContent['template']);
        $this->assertEquals('KYC Verification Approved', $processedContent['subject']);
        $this->assertEquals('admin@example.com', $processedContent['recipient']);

        $expectedHtml = 'Verification #5 for Adam West (adam.west@example.com) was Approved: ok. URL: https://example.com/';
        $this->assertEquals($expectedHtml, $processedContent['html']);
        $expectedTxt = 'TXT: #5 Approved ok https://example.com/';
        $this->assertEquals($expectedTxt, $processedContent['txt']);

        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Adam West', $templateVars['{customer_name}']);
        $this->assertEquals('adam.west@example.com', $templateVars['{customer_email}']);
        $this->assertEquals(10, $templateVars['{customer_id}']);
        $this->assertEquals(5, $templateVars['{verification_id}']);
        $this->assertEquals('Approved', $templateVars['{status_label}']);
        $this->assertEquals('ok', $templateVars['{status_message}']);
        $this->assertEquals('https://example.com/', $templateVars['{admin_verification_url}']);
    }

    public function testSendAdminStatusChangeNotificationAdminEmailsEmpty()
    {
        $verification = ['id_kyc_verification' => 5, 'status' => 'approved'];
        $customer = ['firstname' => 'Adam', 'lastname' => 'West', 'email' => 'adam.west@example.com', 'id_customer' => 10];

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('')
            ->once();

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertFalse($result);
    }

    public function testSendAdminStatusChangeNotificationExceptionHandling()
    {
        $verification = [
            'id_kyc_verification' => 6,
            'status' => 'approved',
        ];

        $customer = [
            'firstname' => 'Eve',
            'lastname' => 'Adams',
            'email' => 'eve.adams@example.com',
            'id_customer' => 11,
        ];

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andThrow(new \Exception('Translator failed'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with(\Mockery::pattern('/KYC admin notification error:/'), 3, null, 'Pskyc');

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertFalse($result);
    }

    public function testSendAdminStatusChangeNotificationMultipleEmails()
    {
        $verification = [
            'id_kyc_verification' => 7,
            'status' => 'approved',
        ];

        $customer = [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane.doe@example.com',
            'id_customer' => 12,
        ];

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin1@example.com, admin2@example.com')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin1@example.com')
            ->andReturn(true)
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin2@example.com')
            ->andReturn(true)
            ->once();

        $this->setupContextExpectations();
        \Mail::shouldReceive('Send')
            ->once()
            ->withArgs(function ($langId, $template, $subject, $vars, $to) {
                return $to === 'admin1@example.com' && $template === 'admin_verification_status';
            })
            ->andReturn(true);
        \Mail::shouldReceive('Send')
            ->once()
            ->withArgs(function ($langId, $template, $subject, $vars, $to) {
                return $to === 'admin2@example.com' && $template === 'admin_verification_status';
            })
            ->andReturn(true);

        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('Approved');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertTrue($result);

        $processedContent = \Mail::getLastProcessedContent();
        $this->assertEquals('admin2@example.com', $processedContent['recipient']);
    }

    public function testSendAdminStatusChangeNotificationInvalidEmailsFallback()
    {
        $verification = [
            'id_kyc_verification' => 8,
            'status' => 'approved',
        ];

        $customer = [
            'firstname' => 'Paul',
            'lastname' => 'Smith',
            'email' => 'paul.smith@example.com',
            'id_customer' => 13,
        ];

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('invalid-email')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('invalid-email')
            ->andReturn(false)
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_EMAIL')
            ->andReturn('shop@example.com')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('shop@example.com')
            ->andReturn(true)
            ->once();

        \Configuration::shouldReceive('get')
            ->with('PS_SHOP_NAME')
            ->andReturn('Test Shop')
            ->once();

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        $this->translatorMock->shouldReceive('trans')
            ->with('Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('Approved');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Approved', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Approved');

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertTrue($result);

        $processedContent = \Mail::getLastProcessedContent();
        $this->assertEquals('shop@example.com', $processedContent['recipient']);
    }

    public function testSendAdminStatusChangeNotificationTemplateVariables()
    {
        $verification = [
            'id_kyc_verification' => 9,
            'status' => 'rejected',
            'admin_note' => 'Bad docs',
        ];

        $customer = [
            'firstname' => 'Sarah',
            'lastname' => 'Connor',
            'email' => 's.connor@example.com',
            'id_customer' => 14,
        ];

        \Mail::resetMockState();
        \Mail::setMockTemplateContent(
            'Verification #{verification_id} for {customer_name} was {status_label}: {status_message}. URL: {admin_verification_url}',
            'TXT: #{verification_id} {status_label} {status_message} {admin_verification_url}'
        );

        \Configuration::shouldReceive('get')
            ->with('PSKYC_ADMIN_EMAILS')
            ->andReturn('admin@example.com')
            ->once();

        \Validate::shouldReceive('isEmail')
            ->with('admin@example.com')
            ->andReturn(true)
            ->once();

        $this->setupContextExpectations();
        $this->setupMailExpectations(true);

        $this->translatorMock->shouldReceive('trans')
            ->with('Rejected', [], 'Modules.Pskyc.Shop')
            ->andReturn('Rejected');

        $this->translatorMock->shouldReceive('trans')
            ->with('KYC Verification Rejected', [], 'Modules.Pskyc.Shop')
            ->andReturn('KYC Verification Rejected');

        $result = $this->service->sendAdminStatusChangeNotification($verification, $customer);

        $this->assertTrue($result);

        $processedContent = \Mail::getLastProcessedContent();
        $this->assertEquals('admin_verification_status', $processedContent['template']);
        $this->assertEquals('KYC Verification Rejected', $processedContent['subject']);
        $this->assertEquals('admin@example.com', $processedContent['recipient']);

        $expectedHtml = 'Verification #9 for Sarah Connor was Rejected: Bad docs. URL: https://example.com/';
        $this->assertEquals($expectedHtml, $processedContent['html']);
        $expectedTxt = 'TXT: #9 Rejected Bad docs https://example.com/';
        $this->assertEquals($expectedTxt, $processedContent['txt']);

        $templateVars = $processedContent['templateVars'];
        $this->assertEquals('Sarah Connor', $templateVars['{customer_name}']);
        $this->assertEquals('s.connor@example.com', $templateVars['{customer_email}']);
        $this->assertEquals(14, $templateVars['{customer_id}']);
        $this->assertEquals(9, $templateVars['{verification_id}']);
        $this->assertEquals('Rejected', $templateVars['{status_label}']);
        $this->assertEquals('Bad docs', $templateVars['{status_message}']);
        $this->assertEquals('https://example.com/', $templateVars['{admin_verification_url}']);
    }
}
